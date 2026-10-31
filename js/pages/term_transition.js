/**
 * Term Transition Controller
 * Wizard to close Term 1, roll over timetable, and activate Term 2.
 * API: /academic/term-transition/context, /academic/term-transition/execute
 */
const termTransitionController = {

  _step:       1,
  _currentTerm: null,
  _nextTerm:    null,
  _allTerms:    [],
  _timetableSlots: [],

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await this._loadTerms();
    await this._loadTimetableStats();
    this._renderStep1();
  },

  // ── Load data ─────────────────────────────────────────────────────────────
  _loadTerms: async function () {
    try {
      const r = await callAPI('/academic/term-transition/context', 'GET');
      const payload = r?.data || r || {};
      this._allTerms = Array.isArray(payload.terms) ? payload.terms : [];
      this._resultReadiness = payload.result_readiness || null;
      this._lessonPlanSummary = payload.lesson_plan_summary || null;
      this._currentTerm = payload.current_term || this._allTerms.find(t => (t.status || '').toLowerCase() === 'current');
      this._nextTerm = payload.next_term || this._allTerms.find(t =>
        (t.status || '').toLowerCase() === 'upcoming' &&
        Number(t.term_id) === Number(this._currentTerm?.term_id || 0) + 1
      );

      const badge = document.getElementById('ttCurrentTermBadge');
      if (badge && this._currentTerm) {
        badge.textContent = `Current: ${this._currentTerm.name} ${this._currentTerm.academic_year_id || ''}`;
      }
      const closeBtn = document.getElementById('ttCloseTermBtn');
      if (closeBtn && this._currentTerm) {
        closeBtn.textContent = `Close ${this._currentTerm.name}`;
      }
      this._renderResultReadiness();
      const nameEl = document.getElementById('ttCurrentTermName');
      if (nameEl && this._currentTerm) nameEl.textContent = this._currentTerm.name;

      // Dynamic labels so the wizard works for any term pair (T1->T2, T2->T3).
      if (this._currentTerm) {
        const lbl1 = document.getElementById('ttStepLbl1');
        if (lbl1) lbl1.textContent = `Review ${this._currentTerm.name}`;
        const confirmName = document.getElementById('ttConfirmTermName');
        if (confirmName) confirmName.textContent = this._currentTerm.name;
      }
      if (this._nextTerm) {
        const n = this._nextTerm.name;
        const lbl4 = document.getElementById('ttStepLbl4');
        if (lbl4) lbl4.textContent = `Setup ${n}`;
        const hdr = document.getElementById('ttNextTermHeader');
        if (hdr) hdr.textContent = `${n} Setup`;
        const sLbl = document.getElementById('ttNextTermStartLabel');
        if (sLbl) sLbl.textContent = `${n} Start Date`;
        const eLbl = document.getElementById('ttNextTermEndLabel');
        if (eLbl) eLbl.textContent = `${n} End Date`;
        const chk = document.getElementById('ttNextTermChecklistTitle');
        if (chk) chk.textContent = `${n} Checklist`;
        const actHdr = document.getElementById('ttActivateHeader');
        if (actHdr) actHdr.textContent = `Activate ${n}`;
        const doneTitle = document.getElementById('ttDoneTitle');
        if (doneTitle) doneTitle.textContent = `${n} is Now Active!`;
      }
      const rolloverBtn = document.getElementById('ttRolloverBtn');
      if (rolloverBtn) rolloverBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Roll Over Timetable' + (this._nextTerm ? ` to ${this._nextTerm.name}` : '');

      const proceed = document.querySelector('#ttStep1 button[onclick*="goStep(2)"]');
      if (proceed) {
        proceed.disabled = !this._currentTerm || !this._nextTerm;
        proceed.title = this._nextTerm ? '' : 'There is no upcoming term configured for this academic year';
      }

      // Pre-fill next term dates if we have them
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
      const r = await callAPI('/academic/timetable-stats' + (params ? '?' + params : ''), 'GET');
      const payload = r?.data || r || {};
      this._timetableSlots = Array.isArray(payload.slots) ? payload.slots : [];
      const classes  = payload.class_count ?? [...new Set(this._timetableSlots.map(s => s.class_id))].length;
      const teachers = payload.teacher_count ?? [...new Set(this._timetableSlots.map(s => s.teacher_id).filter(Boolean))].length;
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
      const [rAttn, rResults] = await Promise.allSettled([
        callAPI('/attendance/academic-summary?term_id=' + (term?.id || ''), 'GET'),
        callAPI('/academic/grading-results?term_id=' + (term?.id || ''), 'GET'),
      ]);

      const approved = Number(this._lessonPlanSummary?.approved || 0);
      const pending = Number(this._lessonPlanSummary?.pending || 0);

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
      this._currentTerm.status = 'pending_close';
      showNotification(`${this._currentTerm.name} is prepared for closure. No database change is made until final activation.`, 'info');
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
      showNotification('The next term must exist before rolling over the timetable.', 'warning');
      return;
    }
    const errEl = document.getElementById('ttRolloverError');
    if (errEl) errEl.classList.add('d-none');
    this._set('ttRolloverStatus', 'Ready for atomic transition');
    showNotification('Timetable rollover is prepared. It will run atomically during final activation.', 'info');
    this.goStep(4);
  },

  // ── STEP 4: Term 2 setup ──────────────────────────────────────────────────
  _renderTerm2Setup: function () {
    const cl = document.getElementById('ttSetupChecklist');
    if (!cl) return;
    const term2 = this._nextTerm;
    const checks = [
      { label: 'Next term dates configured', done: !!(term2?.start_date && term2?.end_date) },
      { label: 'Timetable rollover selected', done: !!document.getElementById('ttKeepTeachers') || !!document.getElementById('ttKeepRooms') },
      { label: 'Exam schedule (set after activation)', done: false },
      { label: 'Schemes of work due', done: false },
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
    const errEl = document.getElementById('ttSetupError');

    if (!start || !end) {
      if (errEl) { errEl.textContent = 'Start and end dates are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    if (this._nextTerm) {
      this._nextTerm.start_date = start;
      this._nextTerm.end_date = end;
      showNotification('Next-term dates will be saved atomically during activation.', 'info');
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
    if (!(await window.confirmAction('Confirm term transition', `Close ${this._currentTerm.name} and activate ${this._nextTerm.name}? This is the final atomic action.`))) return;
    const btn   = document.getElementById('ttActivateBtn');
    const errEl = document.getElementById('ttActivateError');
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div> Activating…'; }
    if (errEl) errEl.classList.add('d-none');

    try {
      const keepTeachers = document.getElementById('ttKeepTeachers')?.checked ?? true;
      const keepRooms = document.getElementById('ttKeepRooms')?.checked ?? true;
      const readiness = this._resultReadiness;
      const forceReason = readiness && !readiness.ready
        ? (document.getElementById('ttForceReason')?.value || '').trim()
        : '';
      await callAPI('/academic/term-transition/execute', 'POST', {
        from_term_id: this._currentTerm.id,
        to_term_id: this._nextTerm.id,
        academic_year_id: this._nextTerm.academic_year_id,
        rollover_timetable: this._timetableSlots.length > 0,
        keep_teachers: keepTeachers,
        keep_rooms: keepRooms,
        ...(forceReason ? { force_reason: forceReason } : {}),
        dates: {
          start_date: this._nextTerm.start_date,
          end_date: this._nextTerm.end_date,
          half_term_start: document.getElementById('ttMidtermStart')?.value || null,
          half_term_end: document.getElementById('ttMidtermEnd')?.value || null,
        },
      });
      // Let the whole system know the school is now in a new term.
      if (window.AcademicContext) await window.AcademicContext.refresh();
      if (window.DataStore) window.DataStore.invalidateMany(['academic', 'timetable', 'attendance']);
      showNotification(`${this._nextTerm.name} is now active!`, 'success');
      this.goStep('done');
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Activation failed.'; errEl.classList.remove('d-none'); }
      if (btn) { btn.disabled = false; btn.innerHTML = `<i class="bi bi-play-fill me-1"></i> Activate ${this._nextTerm?.name || 'Next Term'} Now`; }
    }
  },

  // ── Result readiness ──────────────────────────────────────────────────────
  // A term cannot carry unapproved marks into the record: TermResultsService
  // only consumes approved evidence. Surface the blockers before the operator
  // commits to closing the term.
  _renderResultReadiness: function () {
    const el = document.getElementById('ttResultsReadiness');
    if (!el) return;
    const r = this._resultReadiness;
    if (!r || r.ready) { el.classList.add('d-none'); el.innerHTML = ''; return; }

    const parts = [];
    if (Number(r.pending_registers) > 0) parts.push(`${this._esc(r.pending_registers)} learning-area register(s) still awaiting approval`);
    if (Number(r.pending_learners) > 0) parts.push(`${this._esc(r.pending_learners)} learner result(s) still awaiting approval`);

    el.innerHTML = `<div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Close the assessment cycle first</div>`
      + `<p class="mb-2 small">${parts.join(' and ')}. Marks that are recorded but never approved are not part of the official term results.</p>`
      + `<p class="mb-2 small text-muted">Publish the exam results, or send the registers back to the subject teachers from the exam period results view. If this term genuinely holds no marks, you may continue — the reason is recorded.</p>`
      + `<label class="form-label small fw-semibold" for="ttForceReason">Reason for closing without approved results</label>`
      + `<input class="form-control form-control-sm" type="text" id="ttForceReason" maxlength="255" placeholder="Required only if you continue with results outstanding">`;
    el.classList.remove('d-none');
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

window.termTransitionController = termTransitionController;
