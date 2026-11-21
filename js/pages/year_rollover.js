/**
 * Year Transition / Rollover Controller
 * Drives the canonical 23-stage AcademicYearTransitionWorkflow:
 * All persistent stages are defined by migration 100. Preparation entered in
 * the create form records stages 1-4 atomically; the remaining stages remain
 * resumable and are displayed in their canonical order below.
 *
 * API:
 *   POST /academic/year-transition/start-workflow
 *   POST /academic/year-transition/setup-new-year
 *   POST /academic/year-transition/close-year
 *   POST /academic/year-transition/execute-promotions
 *   POST /academic/year-transition/migrate-competency-baselines
 *   POST /academic/year-transition/validate-readiness
 *   PUT  /academic/years/set-current/{id}
 *   GET  /academic/workflow/status?workflow_type=year-transition&instance_id=
 */
window.yearRolloverController = {

  _currentYear: null,
  _terms: [],
  _instanceId: null,
  _workflowData: null,
  _running: false,
  _log: [],
  _promotionCandidates: [],
  _promotionRules: [],
  _promotionUnavailableIds: new Set(),
  _currentStage: null,
  _promotionBoardLoaded: false,
  _targetYear: null,
  _activePhase: null,
  _classDraftRows: [],
  _classDraftIsTargetYear: false,
  _setupDrafts: {},
  _localSetupEdits: {},
  _targetCalendar: null,

  _phaseStages: {
    year: ['confirm_current_year', 'create_next_year', 'enter_year_term_dates', 'generate_calendar'],
    classes: ['configure_classes_streams'],
    curriculum: ['configure_learning_areas'],
    staff: ['configure_teachers'],
    fees: ['prepare_fee_structures', 'approve_fee_structures'],
    promotions: ['review_promotion_candidates', 'assign_promotion_decisions', 'assign_target_streams', 'create_new_year_enrollments'],
    // Billing and balance carry-forward now happen at promotion time, so the
    // finance phase only closes the outgoing year, migrates baselines and
    // performs the final cutover.
    // The outgoing year is CLOSED (active - completed: readable by all,
    // editable by school admin / system admin / director or a user granted
    // academic_years_edit_completed) during the final cutover. The system has
    // no archiving; every year stays visible.
    finance: ['migrate_baselines', 'close_outgoing_year', 'activate_new_year_term_one'],
  },

  _phaseNotes: {
    year: 'Set the target year and dates.',
    classes: 'Adjust target classes, streams and capacity.',
    curriculum: 'Confirm learning areas offered by class.',
    staff: 'Confirm staff expected to continue. Assign teachers after the new year and term are active.',
    fees: 'Review target-year fees.',
    promotions: 'Choose each learner’s promotion decision.',
    finance: 'Review balances and complete year-end cutover.',
  },

  _stages: [
    ['confirm_current_year', 'Confirm current academic year', 'Verify the outgoing year and its current context.', 'bi-shield-check', 'primary'],
    ['create_next_year', 'Create/find immediate next year', 'Use the canonical YYYY/YYYY+1 year code.', 'bi-calendar-plus', 'primary'],
    ['enter_year_term_dates', 'Enter year and term dates', 'Record year, term and optional half-term dates.', 'bi-calendar2-range', 'primary'],
    ['generate_calendar', 'Generate calendar', 'Derive school weeks and calendar days from the dates.', 'bi-calendar-week', 'info'],
    ['configure_classes_streams', 'Configure classes and streams', 'Prepare the target classes and administrator-selected streams.', 'bi-diagram-3', 'info'],
    ['configure_learning_areas', 'Confirm learning areas by class', 'Choose the learning areas offered in each target-year class.', 'bi-book', 'info'],
    ['configure_teachers', 'Confirm staff continuity', 'Record who is expected to continue; defer class and subject assignments until term setup.', 'bi-person-check', 'info'],
    ['prepare_fee_structures', 'Prepare fee structures', 'Copy fee structures as drafts for the target year.', 'bi-cash-stack', 'warning'],
    ['approve_fee_structures', 'Review and approve fee structures', 'Fee structures must be approved before billing readiness.', 'bi-check2-square', 'warning'],
    ['review_promotion_candidates', 'Review promotion candidates', 'Review each continuing learner and their target class.', 'bi-people', 'primary'],
    ['assign_promotion_decisions', 'Assign promotion decisions', 'Promote, retain, transfer, graduate or otherwise decide each learner.', 'bi-person-check', 'primary'],
    ['assign_target_streams', 'Assign each learner to a target class and stream', 'Assign in batches; saved progress can be resumed later.', 'bi-diagram-2', 'primary'],
    ['create_new_year_enrollments', 'Create new-year enrollments', 'Create target-year enrollment records without overwriting history.', 'bi-person-plus', 'info'],
    ['migrate_baselines', 'Migrate competency baselines', 'Carry CBC competency baselines into the new year.', 'bi-graph-up-arrow', 'primary'],
    ['close_outgoing_year', 'Close the outgoing year', 'Confirm the closing. The outgoing year stays current until its last day; on the new year\'s start date it becomes completed (readable by everyone, editable by school admin, system admin or director).', 'bi-folder-check', 'secondary'],
    ['activate_new_year_term_one', 'Certify the new academic year', 'Run every readiness check. The system then switches to the new year on its start date — the outgoing year stays operational until its last day.', 'bi-patch-check', 'success'],
  ].map(([code, label, desc, icon, color]) => ({ code, label, desc, icon, color })),

  _STORE_KEY: 'kingsway_yr_transition_instance',
  _PHASE_STORE_KEY: 'kingsway_yr_transition_active_phase',
  _phaseOrder: ['year', 'classes', 'curriculum', 'staff', 'fees', 'promotions', 'finance'],

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.allSettled([this._loadStatus(), this._loadYears()]);
    await this._loadTargetCalendar();
    await this._resumeInstance();
    this._bindTargetYear();
    this._bindClassDraft();
    this._bindRolloverSummary();
    this._bindHalfTermToggles();
    this._bindPhaseNavigation();
    this._restorePhaseDraft('year');
    this._bindDateWeekdays();
    await this.loadClassSetupSource();
    this._render();
    // Restoring a saved tab activates it directly, so its click handler never
    // runs. Attach/load the workflow for a restored setup tab during startup.
    const sourceBackedPhases = ['curriculum', 'staff', 'fees', 'promotions', 'finance'];
    if (sourceBackedPhases.includes(this._activePhase)) {
      if (!this._instanceId && this._targetYear) await this.startExistingYearWorkflow(true);
      else if (this._instanceId) await this.loadSetupDrafts();
      if (this._activePhase === 'finance' && this._instanceId) await this.loadRolloverSummary(true);
      this._render();
    }
  },

  _bindRolloverSummary: function () {
    const button = document.getElementById('yrRefreshRolloverSummary');
    if (!button || button.dataset.bound) return;
    button.dataset.bound = '1';
    button.addEventListener('click', () => this.loadRolloverSummary(true));

    const handover = document.getElementById('yrRefreshHandover');
    if (handover && !handover.dataset.bound) {
      handover.dataset.bound = '1';
      handover.addEventListener('click', () => this._loadHandover());
    }
  },

  _bindHalfTermToggles: function () {
    document.querySelectorAll('.yr-half-term-toggle').forEach(toggle => {
      const update = () => {
        const fields = document.getElementById(`yrT${toggle.dataset.term}HalfTermFields`);
        if (fields) fields.classList.toggle('d-none', !toggle.checked);
      };
      toggle.addEventListener('change', update);
      update();
    });
  },

  _bindDateWeekdays: function () {
    document.querySelectorAll('#yrCreateForm input[type="date"]').forEach(input => {
      const output = document.getElementById(`${input.id}Weekday`);
      const update = () => {
        if (!output) return;
        if (!input.value) { output.textContent = ''; return; }
        const [year, month, day] = input.value.split('-').map(Number);
        output.textContent = new Intl.DateTimeFormat('en-KE', {
          weekday: 'long', timeZone: 'Africa/Nairobi',
        }).format(new Date(Date.UTC(year, month - 1, day, 12)));
      };
      input.addEventListener('input', update);
      input.addEventListener('change', update);
      update();
    });
  },

  _loadTargetCalendar: async function () {
    const host = document.getElementById('yrCalendarSummary');
    if (!host) return;
    const yearId = this._targetYear?.id;
    if (!yearId) {
      host.innerHTML = '<div class="card-body py-2 text-muted small">Select a target year to view its saved calendar and holidays.</div>';
      return;
    }
    host.innerHTML = '<div class="card-body py-2 text-muted small">Loading calendar…</div>';
    try {
      const response = await callAPI(`/academic/calendar/get/${encodeURIComponent(yearId)}`, 'GET');
      const calendar = response?.data || response;
      this._targetCalendar = calendar;
      const terms = Array.isArray(calendar?.terms) ? calendar.terms : [];
      const days = Array.isArray(calendar?.calendar_days) ? calendar.calendar_days : [];
      const holidays = Array.isArray(calendar?.holidays) ? calendar.holidays : [];
      const termRows = terms.map(term => `<tr><td>${this._esc(term.term_name)}</td><td>${this._esc(term.opening_date)} (${this._weekday(term.opening_date)})</td><td>${this._esc(term.closing_date)} (${this._weekday(term.closing_date)})</td><td>${Number(term.weeks || 0)}</td><td>${Number(term.days || 0)}</td></tr>`).join('');
      const dayRows = days.map(day => {
        const week = day.term_name ? `${day.term_name} · Week ${day.week_number}` : 'School break';
        return `<tr><td>${this._esc(day.date)}</td><td>${this._esc(day.weekday)}</td><td>${this._esc(week)}</td><td>${this._esc(day.day_type_name || day.day_type)}</td><td>${this._esc(day.event || '')}</td></tr>`;
      }).join('');
      const estimated = holidays.filter(item => item.description?.toLowerCase().includes('estimate')).length;
      const canExport = !window.AuthContext?.canExport || AuthContext.canExport('academic');
      const canPrint = !window.AuthContext?.canPrint || AuthContext.canPrint('academic');
      host.innerHTML = `<div class="card-body py-2"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><strong>${this._esc(this._targetYear.year_code)} · Africa/Nairobi</strong><div class="d-flex align-items-center gap-2"><span>${Number(calendar?.total_weeks || 0)} term weeks · ${days.length} dates · ${holidays.length} holidays${estimated ? ` · ${estimated} estimated` : ''}</span>${canExport ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-kw-onclick="yearRolloverController.exportTargetCalendar()">Export CSV</button>' : ''}${canPrint ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-kw-onclick="yearRolloverController.printTargetCalendar()">Print</button>' : ''}</div></div><div class="table-responsive mt-2"><table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Term</th><th>Opens</th><th>Closes</th><th>Weeks</th><th>School days</th></tr></thead><tbody>${termRows || '<tr><td colspan="5">No term calendar saved.</td></tr>'}</tbody></table></div><div class="table-responsive mt-3 yr-calendar-days-scroll"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th>Date</th><th>Day</th><th>Week</th><th>Day type</th><th>Event</th></tr></thead><tbody>${dayRows || '<tr><td colspan="5">No calendar dates are saved for this year.</td></tr>'}</tbody></table></div></div>`;
    } catch (error) {
      this._targetCalendar = null;
      host.innerHTML = `<div class="card-body py-2 text-danger small">Calendar could not be loaded: ${this._esc(error?.message || 'request failed')}</div>`;
    }
  },

  _weekday: function (date) {
    if (!date) return '';
    const [year, month, day] = String(date).split('-').map(Number);
    return new Intl.DateTimeFormat('en-KE', { weekday: 'long', timeZone: 'Africa/Nairobi' })
      .format(new Date(Date.UTC(year, month - 1, day, 12)));
  },

  exportTargetCalendar: async function () {
    if (!this._targetCalendar) return;
    if (window.AuthContext?.canExport && !AuthContext.canExport('academic')) return showNotification('You do not have permission to export this calendar.', 'warning');
    const quote = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Date', 'Weekday', 'Term / week', 'Day type', 'Event', 'Description']];
    for (const day of this._targetCalendar.calendar_days || []) {
      rows.push([day.date, day.weekday, day.term_name ? `${day.term_name} · Week ${day.week_number}` : 'School break', day.day_type_name || day.day_type, day.event || '', day.description || '']);
    }
    const csv = rows.map(row => row.map(quote).join(',')).join('\r\n');
    try {
      await window.KingswayFileLifecycle?.exportText(csv, `academic_calendar_${this._targetYear?.year_code || 'year'}.csv`, 'text/csv;charset=utf-8;');
    } catch (error) {
      showNotification(error?.message || 'Calendar export failed.', 'danger');
    }
  },

  printTargetCalendar: function () {
    if (window.AuthContext?.canPrint && !AuthContext.canPrint('academic')) return showNotification('You do not have permission to print this calendar.', 'warning');
    window.print();
  },

  // ── Load data ─────────────────────────────────────────────────────────────
  _loadStatus: async function () {
    try {
      const r = await callAPI('/academic/year-rollover-status', 'GET');
      this._status = r?.data || r;
    } catch (e) {
      this._status = {};
    }
    await this._loadHandover();
  },

  // ── Soft handover ────────────────────────────────────────────────────────
  // The outgoing year stays operational until its last day; the target year is
  // prepared alongside it and AcademicActivationService::sweep() switches them
  // on the target's start date. This panel shows that state to the operator.
  _loadHandover: async function () {
    try {
      const r = await callAPI('/academic/activation-state', 'GET');
      this._handover = r?.data || r;
    } catch (e) {
      this._handover = null;
      console.warn('Could not load academic handover state:', e);
    }
    this._renderHandover();
  },

  _renderHandover: function () {
    const panel = document.getElementById('yrHandoverPanel');
    if (!panel) return;
    const h = this._handover;
    const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };

    if (!h) {
      panel.classList.add('d-none');
      return;
    }
    panel.classList.remove('d-none');

    const outgoing = h.operational_year;
    const prepared = h.prepared_year;
    const summary = h.outgoing_summary || {};

    set('yrHandoverOperational', outgoing ? `${outgoing.year_code}` : '—');
    set(
      'yrHandoverOperationalMeta',
      outgoing
        ? `Runs until ${this._fmtDate(outgoing.end_date)} · ${this._esc(summary.current_term || 'no active term')}`
        : 'No operational year'
    );

    set('yrHandoverPrepared', prepared ? `${prepared.year_code}` : '—');
    set(
      'yrHandoverPreparedMeta',
      prepared
        ? `${prepared.certified ? 'Certified' : 'Not certified'} · opens ${this._fmtDate(prepared.start_date)}`
        : 'No target year prepared'
    );

    const switchDate = h.switch_date ? this._fmtDate(h.switch_date) : '—';
    set('yrHandoverSwitchDate', switchDate);
    set(
      'yrHandoverSwitchMeta',
      h.days_until_switch === null || h.days_until_switch === undefined
        ? ''
        : h.days_until_switch <= 0
          ? 'Due now'
          : `${h.days_until_switch} day(s) away`
    );

    const failed = (prepared && prepared.failed_checks) || [];
    set('yrHandoverReadiness', prepared?.certified ? 'Certified' : (prepared ? 'In preparation' : '—'));
    set(
      'yrHandoverReadinessMeta',
      failed.length ? `Fix: ${this._esc(failed.join(', '))}` : (prepared?.certified ? 'All checks passed' : '')
    );

    const notice = document.getElementById('yrHandoverNotice');
    if (notice) {
      let text = '';
      if (h.switch_blocked_reason) {
        text = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this._esc(h.switch_blocked_reason)}</span>`;
      } else if (prepared?.certified) {
        text = `<span class="text-success"><i class="bi bi-check2-circle me-1"></i>Certified. The system switches to ${this._esc(prepared.year_code)} automatically on ${switchDate}. The outgoing year keeps serving the school until then.</span>`;
      } else if (h.terms_out_of_sync > 0) {
        text = `<span class="text-warning"><i class="bi bi-arrow-repeat me-1"></i>${h.terms_out_of_sync} term status(es) no longer match the calendar; the activation sweep will correct them.</span>`;
      }
      notice.innerHTML = text;
    }
  },

  _fmtDate: function (value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('en-KE', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Africa/Nairobi' });
  },

  _loadYears: async function () {
    try {
      const r = await callAPI('/academic/years/list', 'GET');
      const years = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._years = years;
      this._currentYear = years.find(y => y.is_current) || years.find(y => (y.status || '').toLowerCase() === 'active') || years[0] || null;
      const requested = new URLSearchParams(window.location.search).get('target_year_id');
      const currentStart = Number(String(this._currentYear?.year_code || '').slice(0, 4));
      this._targetYear = years.find(y => String(y.id) === String(requested))
        || years.filter(y => !y.is_current && Number(String(y.year_code || '').slice(0, 4)) > currentStart)
          .sort((a, b) => Number(String(a.year_code).slice(0, 4)) - Number(String(b.year_code).slice(0, 4)))[0]
        || null;
      if (this._currentYear) {
        await this._loadTermsForPrefill(this._currentYear.id);
      }
    } catch (e) {
      console.warn('Failed to load academic years:', e);
    }
  },

  _loadTermsForPrefill: async function (yearId) {
    try {
      const r = await callAPI('/academic/terms', 'GET', null, { academic_year_id: yearId });
      const all = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._terms = all;
      this._prefillForm();
    } catch (e) {
      console.warn('Failed to load terms for prefill:', e);
    }
  },

  _resumeInstance: async function () {
    const storedValue = localStorage.getItem(this._STORE_KEY);
    const stored = storedValue && !['undefined', 'null', ''].includes(storedValue) ? storedValue : null;
    if (!stored && storedValue) localStorage.removeItem(this._STORE_KEY);
    if (stored) {
      try {
        const r = await callAPI(
          '/academic/workflow/status?workflow_type=year-transition&instance_id=' + encodeURIComponent(stored),
          'GET',
        );
        const inst = this._instanceFrom(r);
        const storedTarget = String(inst?.data?.to_year_code || inst?.data?.to_year || '').replace(/\s/g, '');
        const selectedTarget = String(this._targetYear?.year_code || '').replace(/\s/g, '');
        if (inst?.id && (!selectedTarget || storedTarget === selectedTarget)) {
          this._adoptInstance(inst);
          return;
        }
      } catch (e) {
        console.warn('Could not resume saved transition instance; searching by target year:', e);
      }
      localStorage.removeItem(this._STORE_KEY);
    }

    const targetCode = String(this._targetYear?.year_code || '');
    if (!targetCode) return;
    try {
      const r = await callAPI('/academic/year-transition/active?to_year=' + encodeURIComponent(targetCode), 'GET');
      const inst = this._instanceFrom(r);
      if (inst?.id) this._adoptInstance(inst);
    } catch (e) {
      console.warn('Could not find active rollover for target year:', e);
    }
  },

  /**
   * The workflow instance from an API reply. callAPI already unwraps the
   * envelope, so the reply IS {id, current_stage, data}; taking `.data`
   * returned the workflow's inner data (no id), so the page never adopted
   * the rollover and opened on a default tab.
   */
  _instanceFrom: function (r) {
    if (r?.id) return r;
    if (r?.data?.id) return r.data;
    return null;
  },

  _adoptInstance: function (inst) {
    this._instanceId = inst.id;
    this._workflowData = inst.data || {};
    if (!this._workflowData.academic_year_id && this._targetYear?.id) {
      this._workflowData.academic_year_id = Number(this._targetYear.id);
    }
    this._currentStage = this._canonicalStage(inst.current_stage_code || inst.current_stage || this._currentStage);
    // Older workflows saved generate_calendar after already creating the year,
    // terms and calendar. Resume them at the first step that still needs work.
    if (this._currentStage === 'generate_calendar' && this._workflowData.academic_year_id && (this._workflowData.term_ids || []).length >= 3) {
      this._currentStage = 'configure_classes_streams';
    }
    localStorage.setItem(this._STORE_KEY, String(this._instanceId));
    this._importWorkflowLog();
  },

  /**
   * Move the page's position FORWARD only. Re-saving an earlier stage (a
   * setup tab, promotions again) must never send the position back, or every
   * later stage shows "not done" and finished stages become runnable again.
   * The server applies the same rule.
   */
  _moveTo: function (code) {
    const target = this._stages.findIndex(s => s.code === code);
    const current = this._stages.findIndex(s => s.code === this._currentStage);
    if (target < 0) return;
    if (current < 0 || target > current) this._currentStage = code;
  },

  _canonicalStage: function (code) {
    const legacy = {
      prepare_calendar: 'generate_calendar',
      setup_new_year: 'review_promotion_candidates',
      configure_operational_context: 'review_promotion_candidates',
      current_year_readiness: 'review_promotion_candidates',
      close_current_year_terms: 'review_promotion_candidates',
      execute_promotions: 'assign_target_streams',
      // Finance billing and balance carry-forward moved to the promotion save,
      // so an older pointer parked on one of these stages resumes at the first
      // step that still requires action.
      carry_forward_finances: 'migrate_baselines',
      generate_obligations: 'migrate_baselines',
      reconcile_balances: 'migrate_baselines',
      migrate_baselines: 'migrate_baselines',
      close_year: 'close_outgoing_year',
      validate_readiness: 'activate_new_year_term_one',
    };
    return legacy[code] || code;
  },

  _prefillForm: function () {
    const cur = this._currentYear;
    if (!cur) return;
    const startYear = Number(String(cur.year_code || '').slice(0, 4));
    const toYear = startYear ? startYear + 1 : '';
    const set = (id, v) => { const el = document.getElementById(id); if (el && !el.value) el.value = v; };
    set('yrToYear', toYear);
  },

  // ── Rendering ─────────────────────────────────────────────────────────────
  _render: function () {
    this._renderPreflight();
    this._renderTargetYear();
    this._renderPhaseTabs();
    this._renderSetupContext();

    const hasInstance = !!this._instanceId;
    // Existing setup sections stay editable after the workflow advances.
    document.getElementById('yrApplyClassDraft')?.classList.toggle('d-none', !hasInstance);
    const createForm = document.getElementById('yrCreateForm');
    if (createForm) createForm.classList.toggle('d-none', hasInstance || !!this._targetYear);

    const info = document.getElementById('yrWfInfo');
    if (info) {
      info.textContent = this._instanceId
        ? `#${this._instanceId} · ${this._workflowData?.from_year || ''} → ${this._workflowData?.to_year || ''}`
        : '';
    }

    if (hasInstance) {
      this._renderStages();
      if (['review_promotion_candidates', 'assign_promotion_decisions', 'assign_target_streams'].includes(this._currentStage) && !this._promotionBoardLoaded) {
        this.loadPromotionBoard().catch(e => showNotification(e.message || 'Could not load promotion assignments.', 'danger'));
      }
    }
    this._renderLog();
  },

  _bindTargetYear: function () {
    const select = document.getElementById('yrTargetYear');
    if (!select || select.dataset.bound) return;
    select.dataset.bound = '1';
    select.addEventListener('change', async () => {
      this._targetYear = this._years.find(y => String(y.id) === select.value) || null;
      await this._loadTargetCalendar();
      this._instanceId = null;
      this._workflowData = null;
      this._currentStage = null;
      this._log = [];
      this._promotionRules = [];
      this._promotionCandidates = [];
      this._promotionUnavailableIds = new Set();
      this._promotionBoardLoaded = false;
      localStorage.removeItem(this._STORE_KEY);
      await this._resumeInstance();
      await this.loadClassSetupSource();
      if (this._instanceId) await this.loadSetupDrafts();
      this._render();
    });
  },

  _bindClassDraft: function () {
    document.getElementById('yrReloadClassDraft')?.addEventListener('click', () => this.loadClassSetupSource(true));
    document.getElementById('yrApplyClassDraft')?.addEventListener('click', () => this.runStage('configure_classes_streams'));
    document.getElementById('yrClassDraftBody')?.addEventListener('input', event => {
      const input = event.target.closest('[data-class-draft-index][data-class-draft-field]');
      if (!input) return;
      const row = this._classDraftRows[Number(input.dataset.classDraftIndex)];
      if (row) {
        row[input.dataset.classDraftField] = input.value;
        this._saveLocalEdit('classes', `class-${row.target_class_id}`, { target_class_id: row.target_class_id, stream_names: row.stream_names, capacity: row.capacity, learner_type_codes: row.learner_type_codes });
      }
    });
    document.getElementById('yrClassDraftBody')?.addEventListener('change', event => {
      const checkbox = event.target.closest('[data-class-learner-type]');
      if (!checkbox) return;
      const row = this._classDraftRows[Number(checkbox.dataset.classDraftIndex)];
      if (!row) return;
      row.learner_type_codes = [...checkbox.closest('tr').querySelectorAll('[data-class-learner-type]:checked')].map(input => input.dataset.typeCode);
      this._saveLocalEdit('classes', `class-${row.target_class_id}`, { target_class_id: row.target_class_id, stream_names: row.stream_names, capacity: row.capacity, learner_type_codes: row.learner_type_codes });
    });
    document.querySelectorAll('[data-save-setup-phase]').forEach(button => button.addEventListener('click', () => this.saveSetupPhase(button.dataset.saveSetupPhase)));
    document.getElementById('yrPhasePanels')?.addEventListener('input', event => this._handleSetupDraftEdit(event));
    document.getElementById('yrPhasePanels')?.addEventListener('change', event => this._handleSetupDraftEdit(event));
    document.getElementById('yrPromotionSearch')?.addEventListener('input', () => this._filterPromotionRows());
    ['yrPromotionClassFilter', 'yrPromotionStreamFilter', 'yrPromotionStatusFilter'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this._filterPromotionRows());
    });
    document.getElementById('yrPromotionClassFilter')?.addEventListener('change', () => this._populatePromotionStreams('yrPromotionClassFilter', 'yrPromotionStreamFilter'));
    document.getElementById('yrBulkSourceClass')?.addEventListener('change', () => {
      this._populatePromotionStreams('yrBulkSourceClass', 'yrBulkSourceStream');
      this._populateBulkTargets();
    });
    document.getElementById('yrBulkSourceStream')?.addEventListener('change', () => this._populateBulkTargets());
    document.getElementById('yrBulkDecision')?.addEventListener('change', () => this._populateBulkTargets());
    document.getElementById('yrAddPromotionRule')?.addEventListener('click', () => this.addPromotionRule());
    document.getElementById('yrApplyPromotionRules')?.addEventListener('click', () => this.applyPromotionRules());
    document.getElementById('yrPromotionRulesBody')?.addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-promotion-rule]');
      if (!remove) return;
      this._promotionRules.splice(Number(remove.dataset.removePromotionRule), 1);
      this._renderPromotionRules();
      this.savePhaseDraft('promotions', true);
    });
    document.getElementById('yrPromotionSelectVisible')?.addEventListener('change', event => {
      document.querySelectorAll('#yrPromotionBoardBody tr[data-student-id]:not([hidden]) .yr-promotion-select')
        .forEach(input => { input.checked = event.target.checked; });
      this._updatePromotionSelectionCount();
    });
    document.getElementById('yrPromotionBoardBody')?.addEventListener('change', event => {
      const row = event.target.closest('tr[data-student-id]');
      if (!row) return;
      if (event.target.matches('.yr-promotion-select')) this._updatePromotionSelectionCount();
      if (event.target.matches('.yr-promotion-decision')) {
        const target = row.querySelector('.yr-target-stream');
        if (target) {
          this._filterPromotionTargetOptions(row);
          target.value = event.target.value === 'graduate' ? '0' : '';
        }
        const check = row.querySelector('.yr-promotion-select');
        if (check) check.checked = true;
      }
      if (event.target.matches('.yr-target-stream')) {
        const check = row.querySelector('.yr-promotion-select');
        if (check) check.checked = true;
      }
      this._updatePromotionSelectionCount();
    });
    document.getElementById('yrExtraChargesBody')?.addEventListener('click', event => {
      const button = event.target.closest('[data-extra-tier-action]');
      if (button) this._changeExtraChargeTier(button.dataset.setupKey, button.dataset.extraTierAction, Number(button.dataset.tierIndex));
    });
    document.getElementById('yrAddLearningArea')?.addEventListener('click', () => this.addLearningAreaDraft());
    document.getElementById('yrAddExtraCharge')?.addEventListener('click', () => this.addExtraChargeDraft());
  },

  _bindPhaseNavigation: function () {
    document.querySelectorAll('[data-phase-save-draft]').forEach(button => button.addEventListener('click', () => this.savePhaseDraft(button.dataset.phaseSaveDraft)));
    document.querySelectorAll('[data-phase-save]').forEach(button => button.addEventListener('click', () => this.saveCurrentPhase(button.dataset.phaseSave)));
    document.querySelectorAll('[data-phase-back]').forEach(button => button.addEventListener('click', () => this._movePhase(button.dataset.phaseBack, -1)));
    document.querySelectorAll('[data-phase-next]').forEach(button => button.addEventListener('click', () => this._movePhase(button.dataset.phaseNext, 1)));
    const panels = document.getElementById('yrPhasePanels');
    ['input', 'change'].forEach(eventName => panels?.addEventListener(eventName, event => {
      if (event.target.closest('#yrCreateForm')) this.savePhaseDraft('year', true);
      if (event.target.closest('#yrPromotionBoard')) this.savePhaseDraft('promotions', true);
    }));
  },

  _movePhase: function (phase, direction) {
    const index = this._phaseOrder.indexOf(phase);
    const next = this._phaseOrder[index + direction];
    if (next) return this.navigatePhase(next);
  },

  _phaseForStage: function (stageCode) {
    return Object.entries(this._phaseStages).find(([, stageCodes]) => stageCodes.includes(stageCode))?.[0] || null;
  },

  _followWorkflowStage: async function () {
    const nextPhase = this._phaseForStage(this._currentStage);
    const currentIndex = this._phaseOrder.indexOf(this._activePhase);
    const nextIndex = this._phaseOrder.indexOf(nextPhase);
    if (nextIndex > currentIndex) await this.navigatePhase(nextPhase);
  },

  navigatePhase: async function (phase) {
    const sourceBackedPhases = ['curriculum', 'staff', 'fees', 'promotions', 'finance'];
    if (sourceBackedPhases.includes(phase)) {
      if (!this._instanceId && this._targetYear) await this.startExistingYearWorkflow(true);
      if (this._instanceId) await this.loadSetupDrafts();
      if (this._hasTargetYearStructure() && (phase === 'promotions' || phase === 'finance')) {
        try {
          if (!this._promotionBoardLoaded) await this.loadPromotionBoard();
          if (phase === 'finance') {
            // Billing and balance carry-forward already happened during the
            // promotion save, so this is a read-only summary of the outcome.
            this._rolloverSummary = null;
            await this.loadRolloverSummary(true);
          }
        } catch (error) {
          showNotification(error.message || 'Could not load learner promotion and finance context.', 'danger');
          return;
        }
      }
    }
    this._activatePhase(phase);
  },

  _hasTargetYearStructure: function () {
    if (!this._instanceId || !this._workflowData?.academic_year_id) return false;
    const classStageIndex = this._stages.findIndex(stage => stage.code === 'configure_classes_streams');
    const currentStageIndex = this._stages.findIndex(stage => stage.code === this._currentStage);
    return currentStageIndex > classStageIndex || (this._workflowData.new_classes || []).length > 0;
  },

  loadRolloverSummary: async function (force = false) {
    if (!this._instanceId) return;
    if (this._running) return;
    if (this._rolloverSummary && !force) return;
    this._running = true;
    try {
      const result = await callAPI('/academic/year-transition/summary?instance_id=' + encodeURIComponent(this._instanceId), 'GET');
      const summary = result?.data || result || {};
      this._rolloverSummary = summary;
      this._renderRolloverSummary();
    } catch (error) {
      showNotification(error.message || 'Could not load the rollover summary.', 'danger');
    } finally {
      this._running = false;
    }
  },

  _money: function (value) {
    return 'KES ' + Number(value || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },

  _renderRolloverSummary: function () {
    if (!document.getElementById('yrRolloverSummaryCards')) return;
    const s = this._rolloverSummary || {};
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    const setHtml = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };
    const decisions = s.decisions || {};
    const billing = s.billing || {};
    const graduates = s.graduates || {};

    const note = document.getElementById('yrRolloverSummaryNote');
    if (note) {
      const source = s.source_year || {};
      const target = s.target_year || {};
      const parts = [
        `Outgoing year: <strong>${this._esc(source.label || 'active - current')}</strong>.`,
        `Target year: <strong>${this._esc(target.label || 'active - upcoming')}</strong>${target.starts_on ? ` (starts ${this._esc(target.starts_on)})` : ''}.`,
      ];
      if (target.starts_on && target.starts_on > new Date().toISOString().slice(0, 10)) {
        parts.push('The outgoing year stays current until the target year starts, so the December holiday and the Term&nbsp;1 intake window can run across both years.');
      }
      parts.push('Nothing is archived: every year stays active and its records stay readable.');
      note.innerHTML = parts.join(' ');
    }

    set('yrSumPromoted', decisions.promoted ?? 0);
    set('yrSumRetained', decisions.retained ?? 0);
    set('yrSumGraduated', decisions.graduated ?? 0);
    set('yrSumBilled', billing.learners_billed ?? 0);
    set('yrSumObligations', billing.obligations ?? 0);
    set('yrSumAmount', this._money(billing.amount_billed));
    set('yrSumArrears', this._money(billing.arrears_carried));
    set('yrSumCredits', this._money(billing.credits_carried));

    const terms = billing.per_term || [];
    setHtml('yrRolloverTermBody', terms.length
      ? terms.map(t => `<tr><td>${this._esc(t.term_name || '—')}</td><td class="text-end">${Number(t.obligations || 0).toLocaleString('en-KE')}</td><td class="text-end">${this._money(t.amount)}</td></tr>`).join('')
      : '<tr><td colspan="3" class="text-center text-muted py-3">No target-year billing has been recorded yet. Fees are billed when promotion decisions are saved.</td></tr>');

    const fees = billing.fee_schedules || {};
    const feeEntries = Object.entries(fees);
    setHtml('yrRolloverFeeStatusBody', feeEntries.length
      ? feeEntries.map(([status, rows]) => `<tr><td>${this._esc(status)}</td><td class="text-end">${Number(rows).toLocaleString('en-KE')}</td></tr>`).join('')
      : '<tr><td colspan="2" class="text-center text-muted py-3">No fee structures recorded for the target year.</td></tr>');

    const graduateNote = document.getElementById('yrRolloverGraduateNote');
    if (graduateNote) {
      graduateNote.textContent = graduates.learners
        ? `${graduates.learners} graduating learner(s) keep a retained balance of ${this._money(graduates.balance_retained)}; they are not billed for the new year.`
        : 'No graduating learners in this rollover.';
    }
  },

  _capturePhaseDraft: function (phase) {
    if (phase === 'year') {
      const fields = ['yrToYear', 'yrStartDate', 'yrEndDate', ...[1, 2, 3].flatMap(n => [`yrT${n}Start`, `yrT${n}End`, `yrT${n}HasHalfTerm`, `yrT${n}HalfStart`, `yrT${n}HalfEnd`])];
      return Object.fromEntries(fields.map(id => { const input = document.getElementById(id); return [id, input ? (input.type === 'checkbox' ? input.checked : input.value) : '']; }));
    }
    if (phase === 'classes') return this._classDraftRows.map(row => ({ ...row }));
    if (phase === 'promotions') return {
      rows: [...document.querySelectorAll('#yrPromotionBoardBody tr[data-student-id]')].map(row => ({
        student_id: Number(row.dataset.studentId), decision: row.querySelector('.yr-promotion-decision')?.value || 'promote',
        target_stream_id: Number(row.querySelector('.yr-target-stream')?.value || 0),
        selected: !!row.querySelector('.yr-promotion-select')?.checked,
      })),
      rules: this._promotionRules || [],
    };
    return this._readLocalEdits()[phase] || {};
  },

  _restorePhaseDraft: function (phase) {
    const snapshot = this._readLocalEdits()?.__phase_snapshots?.[phase];
    if (!snapshot) return;
    if (phase === 'year') Object.entries(snapshot).forEach(([id, value]) => {
      const input = document.getElementById(id);
      if (!input) return;
      if (input.type === 'checkbox') input.checked = !!value;
      else input.value = value;
      if (input.classList.contains('yr-half-term-toggle')) document.getElementById(`yrT${input.dataset.term}HalfTermFields`)?.classList.toggle('d-none', !input.checked);
    });
    if (phase === 'promotions') {
      const savedRows = Array.isArray(snapshot) ? snapshot : (snapshot?.rows || []);
      if (!Array.isArray(snapshot)) this._promotionRules = Array.isArray(snapshot?.rules) ? snapshot.rules : [];
      savedRows.forEach(saved => {
      const row = document.querySelector(`#yrPromotionBoardBody tr[data-student-id="${CSS.escape(String(saved.student_id))}"]`);
      const decision = row?.querySelector('.yr-promotion-decision');
      const stream = row?.querySelector('.yr-target-stream');
      const selected = row?.querySelector('.yr-promotion-select');
      if (decision && saved.decision) decision.value = saved.decision;
      if (stream) {
        this._filterPromotionTargetOptions(row);
        stream.value = saved.target_stream_id != null ? String(saved.target_stream_id) : '';
      }
      if (selected) selected.checked = !!saved.selected;
      });
      this._renderPromotionRules();
      this._updatePromotionSelectionCount();
    }
  },

  savePhaseDraft: function (phase, silent = false) {
    const state = this._readLocalEdits();
    state.__phase_snapshots = state.__phase_snapshots || {};
    state.__phase_snapshots[phase] = this._capturePhaseDraft(phase);
    try {
      localStorage.setItem(this._draftStorageKey(), JSON.stringify(state));
      this._localSetupEdits = state;
      if (!silent) showNotification('Draft saved in this browser. You can return to this tab later.', 'success');
      const phaseStatus = document.getElementById(`yrPhaseDraftStatus-${phase}`);
      if (phaseStatus) phaseStatus.textContent = `Saved in this browser at ${new Date().toLocaleTimeString('en-KE', { timeZone: 'Africa/Nairobi' })}.`;
      const status = document.getElementById(phase === 'classes' ? 'yrClassDraftSaveState' : `yrDraftSaveState-${phase}`);
      if (status && !silent) status.textContent = `Draft saved in this browser at ${new Date().toLocaleTimeString('en-KE', { timeZone: 'Africa/Nairobi' })}.`;
      return true;
    } catch (_) {
      if (!silent) showNotification('Browser storage is unavailable. Use Save to persist this tab to the target year.', 'warning');
      return false;
    }
  },

  saveCurrentPhase: async function (phase) {
    if (phase === 'year') {
      if (!this._instanceId) {
        const result = this._targetYear ? await this.startExistingYearWorkflow() : await this.createNewYear();
        if (this._instanceId) this._activatePhase('classes');
        return result;
      }
      showNotification('The selected academic year and calendar are already saved. Continue setup in the next tab.', 'info');
      this._activatePhase('classes');
      return;
    }
    const selectedId = document.getElementById('yrTargetYear')?.value;
    if (selectedId) {
      this._targetYear = this._years.find(year => String(year.id) === String(selectedId)) || this._targetYear;
    }
    if (this._running) return false;
    if (!this._instanceId && this._targetYear) {
      const started = await this.startExistingYearWorkflow();
      if (!started) return;
    }
    if (!this._instanceId) {
      showNotification('Select or create a target academic year before saving this tab.', 'warning');
      return;
    }
    if (phase === 'classes') {
      const saved = await this.runStage('configure_classes_streams', true, false);
      if (saved) this._movePhase('classes', 1);
      return saved;
    }
    if (['curriculum', 'staff', 'fees'].includes(phase)) {
      const saved = await this.saveSetupPhase(phase);
      if (!saved) return false;
      const stageByPhase = { curriculum: 'configure_learning_areas', staff: 'configure_teachers' };
      const stage = stageByPhase[phase];
      if (stage && this._currentStage === stage) {
        const completed = await this.runStage(stage, false, false);
        if (!completed) return false;
      }
      if (phase === 'fees' && this._phaseStages.fees.includes(this._currentStage)) {
        if (this._currentStage === 'prepare_fee_structures' && !await this.runStage('prepare_fee_structures', false, false)) return false;
        if (this._currentStage === 'approve_fee_structures' && !await this.runStage('approve_fee_structures', false, false)) return false;
        if (this._phaseStages.fees.includes(this._currentStage)) return true;
      }
      this._movePhase(phase, 1);
      return true;
    }
    if (phase === 'promotions') {
      const saved = await this.savePromotionAssignments();
      if (saved && this._phaseStages.finance.includes(this._currentStage)) this._movePhase('promotions', 1);
      return saved;
    }
    if (phase === 'finance') {
      const stage = this._currentStage;
      if (!stage || !this._phaseStages.finance.includes(stage) || stage === 'activate_new_year_term_one') {
        showNotification('Finance progress is already saved. Use the clearly labeled cutover action when the school is ready to activate Term 1.', 'info');
        return;
      }
      return this.runStage(stage);
    }
  },

  _draftStorageKey: function () {
    let userId = 'current-user';
    try { userId = String(AuthContext.getUser?.()?.id || localStorage.getItem('kw_active_user_id') || userId); } catch (_) {}
    return `kingsway_year_setup_drafts_${userId}_${this._targetYear?.id || 'no-target'}`;
  },

  _readLocalEdits: function () {
    try { return JSON.parse(localStorage.getItem(this._draftStorageKey()) || '{}') || {}; }
    catch (_) { return {}; }
  },

  _saveLocalEdit: function (phase, key, values) {
    const state = this._readLocalEdits();
    state[phase] = state[phase] || {};
    state[phase][key] = values;
    try {
      localStorage.setItem(this._draftStorageKey(), JSON.stringify(state));
      const statusPhase = ['staff_continuity', 'extra_charges'].includes(phase) ? (phase === 'staff_continuity' ? 'staff' : 'fees') : phase;
      const label = document.getElementById(`yrDraftSaveState-${statusPhase}`);
      if (label) label.textContent = `Autosaved in this browser at ${new Date().toLocaleTimeString('en-KE', { timeZone: 'Africa/Nairobi' })}. Save to target year when ready.`;
      const classLabel = document.getElementById('yrClassDraftSaveState');
      if (phase === 'classes' && classLabel) classLabel.textContent = `Autosaved in this browser at ${new Date().toLocaleTimeString('en-KE', { timeZone: 'Africa/Nairobi' })}. Apply to target year when ready.`;
    } catch (_) {
      const label = document.getElementById(`yrDraftSaveState-${phase}`);
      if (label) label.textContent = 'Browser storage is unavailable; save this draft to the target year now.';
    }
    this._localSetupEdits = state;
  },

  _handleSetupDraftEdit: function (event) {
    const input = event.target.closest('[data-setup-phase][data-setup-key][data-setup-field]');
    if (!input) return;
    const { setupPhase: phase, setupKey: key, setupField: field } = input.dataset;
    const rows = phase === 'staff' ? this._setupDrafts.staff_continuity : this._setupDrafts[phase];
    const row = (rows || []).find(item => String(item._draft_key) === String(key));
    if (!row) return;
    const value = input.type === 'checkbox' ? input.checked : (input.multiple ? Array.from(input.selectedOptions).map(option => Number(option.value)).filter(Boolean) : (input.type === 'number' ? (input.value === '' ? '' : Number(input.value)) : input.value));
    if (phase === 'curriculum' && field === 'class_id') {
      const classId = Number(input.dataset.classId);
      const classIds = new Set((row.class_ids || []).map(Number));
      if (value) classIds.add(classId); else classIds.delete(classId);
      row.class_ids = [...classIds];
    } else {
      row[field] = value;
    }
    if (phase === 'fees' && field === 'amount') {
      const rowKey = input.dataset.feeRow;
      const feeRow = input.closest('tr');
      const total = [...document.querySelectorAll(`[data-setup-phase="fees"][data-fee-row="${rowKey}"]`)]
        .reduce((sum, fieldInput) => sum + (Number(fieldInput.value) || 0), 0);
      const totalCell = feeRow?.querySelector(`[data-fee-row-total="${rowKey}"]`);
      if (totalCell) totalCell.textContent = total ? total.toLocaleString('en-KE') : '–';
    }
    if (phase === 'extra_charges' && field.startsWith('tier_')) {
      const tierIndex = Number(input.dataset.tierIndex);
      const tierField = field.slice(5);
      row.pricing_tiers = row.pricing_tiers || [];
      if (row.pricing_tiers[tierIndex]) row.pricing_tiers[tierIndex][tierField] = value;
    }
    const editable = {};
    ['name', 'class_ids', 'student_type_ids', 'student_ids', 'parent_ids', 'gl_account_id', 'continuity', 'status', 'planned_weeks', 'notes', 'strand_id', 'sub_strand_id', 'staff_id', 'amount', 'due_date', 'day_of_week', 'time_slot_id', 'learning_area_id', 'teacher_id', 'target_scope', 'admission_condition', 'class_id', 'billing_model', 'billing_frequency', 'visible_on_fee_structure', 'pricing_tiers', 'starts_on', 'ends_on', 'due_day', 'academic_year_term_id'].forEach(name => {
      if (row[name] !== undefined) editable[name] = row[name];
    });
    this._saveLocalEdit(phase, key, { ...row._local_identity, ...editable });
    if (phase === 'extra_charges' && field === 'target_scope') this._renderSetupDrafts();
  },

  loadSetupDrafts: async function () {
    // The workflow may have a target year code before academic_year_id has
    // been copied into its JSON payload. The API resolves the year by code.
    if (!this._instanceId) return;
    const bodies = ['yrCurriculumDraftBody', 'yrStaffContinuityBody', 'yrFeeDraftBody', 'yrExtraChargesBody'];
    bodies.forEach(id => { const body = document.getElementById(id); if (body) body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading target-year setup drafts…</td></tr>'; });
    try {
      const response = await callAPI('/academic/year-transition/setup-drafts?instance_id=' + encodeURIComponent(this._instanceId), 'GET');
      this._setupDrafts = response?.data || response || {};
      this._localSetupEdits = this._readLocalEdits();
      const keyFor = (phase, row) => {
        if (phase === 'curriculum') return String(row.id);
        if (phase === 'fees') return row.id ? String(row.id) : String(row.draft_key || `new-fee-${Date.now()}-${Math.random()}`);
        if (phase === 'extra_charges') return row.id ? String(row.id) : String(row.draft_key || `new-charge-${Date.now()}-${Math.random()}`);
        if (phase === 'staff') return String(row.staff_id);
        return row.id ? String(row.id) : String(row.draft_key || `new-${Date.now()}-${Math.random()}`);
      };
      const phases = { curriculum: this._setupDrafts.learning_areas || [], staff: this._setupDrafts.staff_continuity || [], fees: this._setupDrafts.fees || [], extra_charges: this._setupDrafts.extra_charges || [] };
      Object.entries(phases).forEach(([phase, rows]) => {
        rows.forEach(row => {
          row._draft_key = keyFor(phase, row);
          row._local_identity = phase === 'staff' ? { staff_id: row.staff_id }
            : phase === 'fees' ? { id: row.id, draft_key: row.draft_key, academic_year_class_id: row.academic_year_class_id, academic_year_term_id: row.academic_year_term_id, student_type_id: row.student_type_id, fee_catalog_id: row.fee_catalog_id }
              : phase === 'extra_charges' ? { id: row.id, draft_key: row.draft_key }
                : { id: row.id };
          const localRow = this._localSetupEdits?.[phase]?.[row._draft_key] || {};
          if (phase === 'extra_charges') {
            const savedTiers = localRow.pricing_tiers || row.pricing_tiers || [];
            const hasOutOfScopeTier = savedTiers.some(tier => !['', 'default', 'standard', 'all', 'new_parent', 'existing_parent', 'new', 'existing'].includes(String(tier.condition || '').toLowerCase()));
            row._exclude_from_fee_rollover = !!(
              localRow.class_id || localRow.class_ids?.length || localRow.student_type_ids?.length
              || localRow.student_ids?.length || localRow.parent_ids?.length
              || (localRow.target_scope && !['new_admissions','existing_students','all_students','boarders','day_students'].includes(localRow.target_scope))
              || hasOutOfScopeTier
            );
          }
          Object.assign(row, localRow);
          if (phase === 'extra_charges') {
            row.visible_on_fee_structure = 1;
            if (!['new_admissions','existing_students','all_students','boarders','day_students'].includes(row.target_scope)) row.target_scope = 'new_admissions';
            row.pricing_tiers = (row.pricing_tiers || []).filter(tier => ['', 'default', 'standard', 'all', 'new_parent', 'existing_parent', 'new', 'existing'].includes(String(tier.condition || '').toLowerCase()));
            ['class_id','class_ids','student_type_id','student_type_ids','student_ids','parent_ids'].forEach(field => delete row[field]);
          }
          if (phase === 'extra_charges' && (!Array.isArray(row.pricing_tiers) || !row.pricing_tiers.length) && Number(row.amount) > 0) {
            row.pricing_tiers = [{ label: 'Standard', amount: Number(row.amount), condition: '' }];
          }
        });
      });
      phases.extra_charges = phases.extra_charges.filter(row => !row._exclude_from_fee_rollover);
      Object.entries(this._localSetupEdits?.curriculum || {}).forEach(([key, localRow]) => {
        if (Number(localRow.id || 0) >= 0 || phases.curriculum.some(row => String(row.id) === key)) return;
        phases.curriculum.push({ ...localRow, id: Number(localRow.id), _draft_key: key, _local_identity: { id: Number(localRow.id) } });
      });
      Object.entries(this._localSetupEdits?.extra_charges || {}).forEach(([key, localRow]) => {
        if (Number(localRow.id || 0) !== 0 || phases.extra_charges.some(row => row._draft_key === key)) return;
        const hasOutOfScopeTier = (localRow.pricing_tiers || []).some(tier => !['', 'default', 'standard', 'all', 'new_parent', 'existing_parent', 'new', 'existing'].includes(String(tier.condition || '').toLowerCase()));
        if (localRow.class_id || localRow.class_ids?.length || localRow.student_type_ids?.length || localRow.student_ids?.length || localRow.parent_ids?.length
          || (localRow.target_scope && !['new_admissions','existing_students','all_students','boarders','day_students'].includes(localRow.target_scope))
          || hasOutOfScopeTier) return;
        phases.extra_charges.push({
          ...localRow, id: 0, draft_key: key, _draft_key: key,
          target_scope: ['new_admissions','existing_students','all_students','boarders','day_students'].includes(localRow.target_scope) ? localRow.target_scope : 'new_admissions',
          visible_on_fee_structure: 1,
          pricing_tiers: (localRow.pricing_tiers || []).filter(tier => ['', 'default', 'standard', 'all', 'new_parent', 'existing_parent', 'new', 'existing'].includes(String(tier.condition || '').toLowerCase())),
          _local_identity: { id: 0, draft_key: key }, status: 'draft', is_source_draft: false,
        });
      });
      this._setupDrafts.curriculum = phases.curriculum;
      this._setupDrafts.staff_continuity = phases.staff;
      this._setupDrafts.fees = phases.fees;
      this._setupDrafts.extra_charges = phases.extra_charges;
      this._renderSetupDrafts();
      const curriculumState = document.getElementById('yrDraftSaveState-curriculum');
      if (curriculumState) curriculumState.textContent = `${phases.curriculum.length} learning areas loaded${this._setupDrafts.learning_areas_have_current_year_defaults ? ' with current-year defaults for unconfigured class coverage' : this._setupDrafts.learning_areas_loaded_from_current_year ? ' from the current year' : ' from the target year'}.`;
    } catch (error) {
      bodies.forEach(id => { const body = document.getElementById(id); if (body) body.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-3">${this._esc(error.message || 'Could not load target-year setup drafts.')}</td></tr>`; });
    }
  },

  _staffSelect: function (phase, row, field = 'staff_id') {
    const selected = Number(row[field] || 0);
    const options = this._setupDrafts.staff_options || [];
    return `<select class="form-select form-select-sm" data-setup-phase="${phase}" data-setup-key="${this._esc(row._draft_key)}" data-setup-field="${field}"><option value="">Unassigned</option>${options.map(person => `<option value="${this._esc(person.id)}"${Number(person.id) === selected ? ' selected' : ''}>${this._esc(person.name)}</option>`).join('')}</select>`;
  },

  addLearningAreaDraft: function () {
    const rows = this._setupDrafts.curriculum || (this._setupDrafts.curriculum = []);
    const id = -1 * (Date.now() + rows.length);
    rows.push({ id, name: '', class_ids: [], _draft_key: String(id), _local_identity: { id } });
    this._renderSetupDrafts();
    document.querySelector(`#yrCurriculumDraftBody [data-setup-key="${id}"][data-setup-field="name"]`)?.focus();
  },

  addExtraChargeDraft: function () {
    const rows = this._setupDrafts.extra_charges || (this._setupDrafts.extra_charges = []);
    const key = `new-extra-charge-${Date.now()}-${rows.length}`;
    rows.push({
      id: 0, draft_key: key, _draft_key: key, name: '', amount: '', target_scope: 'new_admissions', admission_condition: 'any_new_admission',
      billing_model: 'paid_separately', billing_frequency: 'one_time', charge_frequency: 'one_time',
      visible_on_fee_structure: 1, pricing_tiers: [{ label: 'Standard', amount: '', condition: '' }], status: 'draft', is_source_draft: false,
      _local_identity: { id: 0, draft_key: key },
    });
    this._renderSetupDrafts();
    document.querySelector(`#yrExtraChargesBody [data-setup-key="${CSS.escape(key)}"][data-setup-field="name"]`)?.focus();
  },

  _changeExtraChargeTier: function (key, action, index = -1) {
    const row = (this._setupDrafts.extra_charges || []).find(item => String(item._draft_key) === String(key));
    if (!row || (Number(row.id) > 0 && row.status !== 'draft')) return;
    row.pricing_tiers = row.pricing_tiers || [];
    if (action === 'add') row.pricing_tiers.push({ label: '', amount: '', condition: '' });
    if (action === 'remove' && index >= 0) row.pricing_tiers.splice(index, 1);
    this._saveLocalEdit('extra_charges', row._draft_key, { ...row._local_identity, pricing_tiers: row.pricing_tiers });
    this._renderSetupDrafts();
  },

  _renderSetupDrafts: function () {
    const rows = this._setupDrafts;
    const put = (id, html, cols) => { const body = document.getElementById(id); if (body) body.innerHTML = html || `<tr><td colspan="${cols}" class="text-center text-muted py-3">No target-year records yet. Prepare classes and streams first.</td></tr>`; };
    const classOptions = rows.class_options || [];
    const curriculumHead = document.getElementById('yrCurriculumDraftHead');
    if (curriculumHead) curriculumHead.innerHTML = `<tr><th>Learning area</th>${classOptions.map(item => `<th class="text-center">${this._esc(item.name)}</th>`).join('')}</tr>`;
    put('yrCurriculumDraftBody', (rows.curriculum || []).map(row => `<tr>
      <td><input class="form-control form-control-sm" aria-label="Learning area name" data-setup-phase="curriculum" data-setup-key="${this._esc(row._draft_key)}" data-setup-field="name" value="${this._esc(row.name)}"></td>
      ${classOptions.map(item => `<td class="text-center"><input type="checkbox" class="form-check-input" aria-label="Offer ${this._esc(row.name)} in ${this._esc(item.name)}" data-setup-phase="curriculum" data-setup-key="${this._esc(row._draft_key)}" data-setup-field="class_id" data-class-id="${this._esc(item.id)}"${(row.class_ids || []).map(Number).includes(Number(item.id)) ? ' checked' : ''}></td>`).join('')}
    </tr>`).join(''), 1 + classOptions.length);
    put('yrStaffContinuityBody', (rows.staff_continuity || []).map(row => `<tr><td>${this._esc(row.name)}</td><td><select class="form-select form-select-sm" data-setup-phase="staff" data-setup-key="${this._esc(row._draft_key)}" data-setup-field="continuity"><option value="undecided"${row.continuity === 'undecided' ? ' selected' : ''}>To be confirmed</option><option value="continuing"${row.continuity === 'continuing' ? ' selected' : ''}>Continuing</option><option value="not_continuing"${row.continuity === 'not_continuing' ? ' selected' : ''}>Not continuing</option></select></td></tr>`).join('') || '<tr><td colspan="2" class="text-center text-muted py-3">No active staff records available. Teaching assignments can be added during term setup.</td></tr>', 2);
    const feeTerms = rows.term_options || [];
    const feeHead = document.getElementById('yrFeeDraftHead');
    if (feeHead) feeHead.innerHTML = `<tr><th>Class</th><th>Learner type</th>${feeTerms.map(term => `<th>${this._esc(term.name)}</th>`).join('')}<th>Total</th></tr>`;
    const feeGroups = new Map();
    // Match the existing fee structure grid: schedule rows are presented as
    // School Fees regardless of the backing catalog code.
    (rows.fees || []).forEach(row => {
      const groupKey = [row.class_id || 0, row.student_type_id || 0].join(':');
      if (!feeGroups.has(groupKey)) feeGroups.set(groupKey, { rows: new Map(), sample: row });
      feeGroups.get(groupKey).rows.set(Number(row.academic_year_term_id || 0), row);
    });
    const feeCell = (group, term) => {
      const row = group.rows.get(Number(term.id));
      if (!row) return '<span class="text-muted">—</span>';
      return `<input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" aria-label="${this._esc(term.name)} fee" data-setup-phase="fees" data-setup-key="${this._esc(row._draft_key)}" data-setup-field="amount" data-fee-row="${this._esc(group.sample.class_id)}:${this._esc(group.sample.student_type_id)}" value="${this._esc(row.amount ?? '')}">`;
    };
    const feeRows = [...feeGroups.values()].sort((a, b) => Number(a.sample.class_id) - Number(b.sample.class_id) || Number(a.sample.student_type_id) - Number(b.sample.student_type_id)).map(group => {
      const row = group.sample;
      const total = feeTerms.reduce((sum, term) => sum + Number(group.rows.get(Number(term.id))?.amount || 0), 0);
      const termCells = feeTerms.map(term => `<td>${feeCell(group, term)}</td>`).join('');
      return `<tr><th scope="row">${this._esc(row.class_name || 'Class')}</th><td>${this._esc(row.student_type_name || 'Learner type')}</td>${termCells}<td class="text-end fw-semibold" data-fee-row-total="${this._esc(row.class_id)}:${this._esc(row.student_type_id)}">${total ? total.toLocaleString('en-KE') : '–'}</td></tr>`;
    }).join('');
    put('yrFeeDraftBody', feeRows || `<tr><td colspan="${3 + feeTerms.length}" class="text-center text-muted py-3">No School Fees have been set up for this target year.</td></tr>`, 3 + feeTerms.length);
    const chargeSelectOptions = (values, selected) => values.map(([value, label]) => `<option value="${value}"${selected === value ? ' selected' : ''}>${label}</option>`).join('');
    put('yrExtraChargesBody', (rows.extra_charges || []).map(row => {
      const key = this._esc(row._draft_key);
      const locked = Number(row.id) > 0 && row.status !== 'draft';
      const disabled = locked ? ' disabled' : '';
      const input = (field, type = 'text', value = row[field] ?? '') => `<input type="${type}" class="form-control form-control-sm" data-setup-phase="extra_charges" data-setup-key="${key}" data-setup-field="${field}" value="${this._esc(value)}"${disabled}>`;
      const select = (field, options) => `<select class="form-select form-select-sm" data-setup-phase="extra_charges" data-setup-key="${key}" data-setup-field="${field}"${disabled}>${options}</select>`;
      const tierConditionOptions = (condition) => {
        const choices = [['default','All eligible families'],['new_parent','New parent'],['existing_parent','Existing parent']];
        const normalized = ({ new: 'new_parent', existing: 'existing_parent' })[String(condition || '')] || String(condition || 'default');
        return choices.map(([value,label]) => `<option value="${this._esc(value)}"${normalized === String(value) ? ' selected' : ''}>${this._esc(label)}</option>`).join('');
      };
      return `<tr><td>${input('name')}</td>
        <td><div class="d-grid gap-1">${select('target_scope', chargeSelectOptions([['new_admissions','New admissions'],['existing_students','Existing learners'],['all_students','All learners'],['boarders','Boarders'],['day_students','Day learners']], row.target_scope || 'new_admissions'))}${String(row.target_scope || 'new_admissions') === 'new_admissions' ? select('admission_condition', chargeSelectOptions([['any_new_admission','Every new admission'],['interview_scheduled','Interview scheduled']], row.admission_condition || 'any_new_admission')) : ''}</div></td>
        <td><div class="d-grid gap-1">${(row.pricing_tiers || []).map((tier, index) => `<div class="d-flex gap-1 align-items-center"><input type="text" class="form-control form-control-sm" aria-label="Tier label" placeholder="Label" value="${this._esc(tier.label || '')}" data-setup-phase="extra_charges" data-setup-key="${key}" data-setup-field="tier_label" data-tier-index="${index}"${disabled}><input type="number" min="0" step="0.01" class="form-control form-control-sm" aria-label="Tier amount" placeholder="Amount" value="${this._esc(tier.amount ?? '')}" data-setup-phase="extra_charges" data-setup-key="${key}" data-setup-field="tier_amount" data-tier-index="${index}"${disabled}>${select('tier_condition', tierConditionOptions(tier.condition || 'default')).replace('data-setup-field="tier_condition"','data-setup-field="tier_condition" data-tier-index="'+index+'"')}<button type="button" class="btn btn-outline-danger btn-sm" aria-label="Remove pricing tier" data-extra-tier-action="remove" data-setup-key="${key}" data-tier-index="${index}"${disabled}><i class="bi bi-x"></i></button></div>`).join('')}<button type="button" class="btn btn-outline-secondary btn-sm" data-extra-tier-action="add" data-setup-key="${key}"${disabled}><i class="bi bi-plus-lg me-1"></i>Add price</button></div></td>
        <td>${row.is_source_draft ? '<span class="badge text-bg-info">Copied from current year</span>' : this._esc(row.status || 'Target year')}</td></tr>`;
    }).join(''), 4);
    ['curriculum','staff','fees'].forEach(phase => {
      const state = document.getElementById(`yrDraftSaveState-${phase}`);
      const hasLocal = !!this._localSetupEdits?.[phase];
      if (state && !hasLocal) state.textContent = phase === 'staff' ? 'Loaded from the active staff roster.' : 'Loaded from target-year setup records.';
    });
  },

  saveSetupPhase: async function (phase) {
    if (!this._instanceId || this._running) return false;
    if (phase === 'curriculum' && !(this._setupDrafts.class_options || []).length) {
      showNotification('Load the target-year classes before saving learning-area coverage.', 'warning');
      return false;
    }
    if (phase === 'fees') {
      for (const row of this._setupDrafts.extra_charges || []) {
        const tiers = row.pricing_tiers || [];
        const touchedTier = tiers.some(tier => String(tier.label || '').trim() || String(tier.amount ?? '').trim() || String(tier.condition || '').trim());
        if (!row.id && !String(row.name || '').trim() && !touchedTier) continue;
        if (!String(row.name || '').trim()) {
          showNotification('Enter a name for each extra charge you have started.', 'warning');
          return false;
        }
        const completeTiers = [];
        for (const tier of tiers) {
          const label = String(tier.label || '').trim();
          const amountText = String(tier.amount ?? '').trim();
          const condition = String(tier.condition || '').trim();
          if (!label && !amountText && !condition) continue;
          if (!label || !Number.isFinite(Number(amountText)) || Number(amountText) <= 0) {
            showNotification(`Enter a label and positive amount for each pricing tier in “${row.name}”.`, 'warning');
            return false;
          }
          completeTiers.push({ label, amount: Number(amountText), condition });
        }
        if (!completeTiers.length) {
          showNotification(`Add at least one pricing tier with a positive amount for “${row.name}”.`, 'warning');
          return false;
        }
        row.pricing_tiers = completeTiers;
        row.amount = completeTiers[0].amount;
      }
    }
    const rows = phase === 'curriculum' ? this._setupDrafts.curriculum.map(row => ({ ...row._local_identity, id: row.id, draft_key: row._draft_key, name: row.name, class_ids: row.class_ids || [] }))
      : phase === 'staff' ? (this._setupDrafts.staff_continuity || []).map(row => ({ staff_id: row.staff_id, continuity: row.continuity || 'undecided' }))
        : phase === 'fees' ? this._setupDrafts.fees.map(row => ({ ...row._local_identity, id: row.id, draft_key: row._draft_key, amount: row.amount, due_date: row.due_date, status: row.status })) : [];
    this._running = true;
    try {
      const apiPhase = phase === 'staff' ? 'staff_continuity' : phase;
      const extraCharges = phase === 'fees' ? (this._setupDrafts.extra_charges || []).map(row => ({
        id: row.id, draft_key: row.draft_key || row._draft_key, name: row.name, description: row.description,
        amount: row.amount, calculation_mode: row.calculation_mode || 'fixed', unit_label: row.unit_label,
        unit_price: row.unit_price, charge_frequency: row.charge_frequency || 'one_time', target_scope: row.target_scope,
        billing_model: row.billing_model || 'paid_separately', billing_frequency: row.billing_frequency || 'one_time',
        visible_on_fee_structure: 1, admission_condition: row.admission_condition || 'any_new_admission', display_order: row.display_order, gl_account_id: row.gl_account_id || null, pricing_tiers: row.pricing_tiers || [], starts_on: row.starts_on || null, ends_on: row.ends_on || null, due_day: row.due_day || null, academic_year_term_id: row.academic_year_term_id || null,
      })) : undefined;
      const result = await callAPI('/academic/year-transition/setup-drafts', 'PUT', { instance_id: this._instanceId, phase: apiPhase, rows, ...(extraCharges ? { extra_charges: extraCharges } : {}) });
      Object.entries(result?.id_map || {}).forEach(([key, id]) => {
        const curriculumRow = (this._setupDrafts.curriculum || []).find(item => item._draft_key === key);
        if (curriculumRow) {
          curriculumRow.id = Number(id);
          curriculumRow._draft_key = String(id);
          curriculumRow._local_identity = { id: Number(id) };
        }
        const feeRow = (this._setupDrafts.fees || []).find(item => item._draft_key === key);
        if (feeRow) {
          feeRow.id = Number(id);
          feeRow._draft_key = String(id);
          feeRow._local_identity = { id: Number(id) };
        }
        const chargeRow = (this._setupDrafts.extra_charges || []).find(item => item._draft_key === key);
        if (chargeRow) {
          chargeRow.id = Number(id);
          chargeRow._draft_key = String(id);
          chargeRow._local_identity = { id: Number(id) };
          chargeRow.is_source_draft = false;
        }
      });
      this._renderSetupDrafts();
      const local = this._readLocalEdits();
      delete local[phase];
      if (phase === 'fees') delete local.extra_charges;
      try { localStorage.setItem(this._draftStorageKey(), JSON.stringify(local)); } catch (_) {}
      this._localSetupEdits = local;
      if (phase === 'curriculum') await this.loadSetupDrafts();
      const state = document.getElementById(`yrDraftSaveState-${phase}`);
      if (state) state.textContent = `Saved ${Number(result?.saved || 0)} changes to the target year.`;
      showNotification('Target-year setup saved.', 'success');
      return true;
    } catch (error) {
      showNotification(error.message || 'Could not save this target-year draft.', 'danger');
      return false;
    } finally {
      this._running = false;
    }
  },

  loadClassSetupSource: async function (notify = false) {
    const body = document.getElementById('yrClassDraftBody');
    if (!body) return;
    if (!this._currentYear) {
      body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No outgoing academic year is selected.</td></tr>';
      return;
    }
    body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading current-year classes and streams…</td></tr>';
    try {
      const result = await callAPI('/academic/year-transition/class-setup-source?from_year=' + encodeURIComponent(this._currentYear.year_code || '') + '&to_year=' + encodeURIComponent(this._targetYear?.year_code || ''), 'GET');
      const rows = Array.isArray(result?.data) ? result.data : (Array.isArray(result) ? result : []);
      this._classDraftIsTargetYear = rows.some(row => Number(row.target_class_id) > 0);
      this._classDraftRows = rows.filter(row => Number(row.target_class_id) > 0).map(row => ({
        ...row,
        learner_type_codes: Array.isArray(row.target_learner_type_codes)
          ? row.target_learner_type_codes
          : (Array.isArray(row.source_learner_type_codes) ? row.source_learner_type_codes : ['DAY']),
        has_target_setup: Array.isArray(row.target_streams) && row.target_streams.length > 0,
        streams: row.target_streams?.length ? row.target_streams : row.streams,
        stream_names: (((row.target_streams?.length ? row.target_streams : row.streams) || []).map(stream => stream.name).filter(Boolean).join(', ')) || 'A',
        capacity: Math.max(1, ...(((row.target_streams?.length ? row.target_streams : row.streams) || []).map(stream => Number(stream.capacity) || 40))),
      }));
      this._localSetupEdits = this._readLocalEdits();
      this._classDraftRows.forEach(row => Object.assign(row, this._localSetupEdits?.classes?.[`class-${row.target_class_id}`] || {}));
      this._renderClassDraft();
      if (notify) showNotification(`Loaded ${this._classDraftRows.length} current-year class drafts.`, 'success');
    } catch (error) {
      body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${this._esc(error.message || 'Could not load the current-year class structure.')}</td></tr>`;
    }
  },

  _renderClassDraft: function () {
    const body = document.getElementById('yrClassDraftBody');
    if (!body) return;
    const title = document.getElementById('yrClassDraftTitle');
    const help = document.getElementById('yrClassDraftHelp');
    if (title) title.textContent = this._classDraftIsTargetYear ? 'Target-year class structure' : 'Current-year class structure draft';
    if (help) help.textContent = this._classDraftIsTargetYear
      ? 'Target-year values are loaded where saved; classes without target-year values start from the current-year draft.'
      : 'Every current-year class stays in the target year. Adjust its streams and capacity; learner promotion is decided separately.';
    const head = document.getElementById('yrClassDraftHead');
    if (head) head.innerHTML = this._classDraftIsTargetYear
      ? '<tr><th>Target-year class</th><th>Saved streams</th><th>Adjust streams (comma separated)</th><th style="width:130px">Capacity per stream</th><th>Learner types offered</th></tr>'
      : '<tr><th>Current-year class</th><th>Target-year class</th><th>Current streams</th><th>Target streams (comma separated)</th><th style="width:130px">Capacity per stream</th><th>Learner types offered</th></tr>';
    if (!this._classDraftRows.length) {
      body.innerHTML = `<tr><td colspan="${this._classDraftIsTargetYear ? 5 : 6}" class="text-center text-muted py-4">No current-year classes were found to prepare.</td></tr>`;
      return;
    }
    const typeChoices = [['DAY', 'Day student'], ['BOARD', 'Full boarder']];
    const typeControls = (row, index) => `<div class="d-flex flex-column gap-1">${typeChoices.map(([code, label]) => `<label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-class-learner-type data-class-draft-index="${index}" data-type-code="${code}" ${row.learner_type_codes.includes(code) ? 'checked' : ''}><span class="form-check-label small">${label}</span></label>`).join('')}</div>`;
    body.innerHTML = this._classDraftRows.map((row, index) => this._classDraftIsTargetYear ? `<tr>
      <td class="fw-semibold">${this._esc(row.target_class_name || 'Target class')}</td>
      <td>${this._esc((row.streams || []).map(stream => `${stream.name} (${stream.capacity})`).join(', ') || 'No streams saved')}<div class="small text-muted">${row.has_target_setup ? 'Target year' : 'Current-year draft'}</div></td>
      <td><input class="form-control form-control-sm" aria-label="Target streams for ${this._esc(row.target_class_name)}" data-class-draft-index="${index}" data-class-draft-field="stream_names" value="${this._esc(row.stream_names)}" placeholder="A, B"></td>
      <td><input type="number" min="1" class="form-control form-control-sm" aria-label="Capacity per stream for ${this._esc(row.target_class_name)}" data-class-draft-index="${index}" data-class-draft-field="capacity" value="${this._esc(row.capacity)}"></td>
      <td>${typeControls(row, index)}</td>
    </tr>` : `<tr>
      <td class="fw-semibold">${this._esc(row.source_class_name)}<div class="small text-muted">${(row.streams || []).length} streams</div></td>
      <td>${this._esc(row.target_class_name || 'Unmapped')}</td>
      <td>${this._esc((row.streams || []).map(stream => `${stream.name} (${stream.capacity})`).join(', ') || 'No streams configured')}</td>
      <td><input class="form-control form-control-sm" aria-label="Target streams for ${this._esc(row.target_class_name)}" data-class-draft-index="${index}" data-class-draft-field="stream_names" value="${this._esc(row.stream_names)}" placeholder="A, B"></td>
      <td><input type="number" min="1" class="form-control form-control-sm" aria-label="Capacity per stream for ${this._esc(row.target_class_name)}" data-class-draft-index="${index}" data-class-draft-field="capacity" value="${this._esc(row.capacity)}"></td>
      <td>${typeControls(row, index)}</td>
    </tr>`).join('');
  },

  _renderSetupContext: function () {
    const context = this._workflowData?.new_year_context || {};
    const yearContext = context.academic_year_context || context;
    const rows = {
      curriculum: [['Year context', yearContext, [['learning_areas_copied', 'learning areas copied'], ['classes_mapped', 'classes mapped']]]],
      staff: [['Year context', yearContext, [['class_teachers_copied', 'class-teacher defaults copied']]]],
      fees: [['Year context', yearContext, [['fee_schedules_copied_as_draft', 'fee rows copied as drafts']]]],
    };
    Object.entries(rows).forEach(([phase, groups]) => {
      const element = document.getElementById(`yrSetupContext-${phase}`);
      if (!element) return;
      const sections = groups.map(([label, source, metrics]) => ({
        label,
        source,
        known: metrics.filter(([key]) => source[key] !== undefined),
      })).filter(section => section.known.length);
      if (!sections.length) {
        element.classList.add('d-none');
        return;
      }
      element.innerHTML = sections.map(section => `<strong>${this._esc(section.label)}</strong><ul class="mb-2 mt-1">${section.known.map(([key, label]) => `<li>${this._esc(section.source[key])} ${this._esc(label)}</li>`).join('')}</ul>`).join('');
      element.classList.remove('d-none');
    });
  },

  _renderTargetYear: function () {
    const select = document.getElementById('yrTargetYear');
    const summary = document.getElementById('yrTargetSummary');
    if (!select || !summary) return;
    const currentStart = Number(String(this._currentYear?.year_code || '').slice(0, 4));
    const targets = (this._years || []).filter(y => !y.is_current && Number(String(y.year_code || '').slice(0, 4)) > currentStart);
    select.innerHTML = '<option value="">Select a target year</option>' + targets.map(y =>
      `<option value="${this._esc(y.id)}">${this._esc(y.year_name || y.year_code)}</option>`
    ).join('');
    select.value = this._targetYear ? String(this._targetYear.id) : '';
    if (!this._targetYear) {
      summary.innerHTML = targets.length ? 'Choose a target year to continue its rollover setup.' : 'No future academic year exists yet. Create one below, then continue here.';
      return;
    }
    const yearLabel = this._targetYear.year_name || this._targetYear.year_code;
    summary.innerHTML = `<strong>${this._esc(yearLabel)}</strong> selected${this._instanceId ? ' · rollover progress is saved' : ' · ready to continue setup'}.
      ${!this._instanceId ? '<button class="btn btn-sm btn-primary ms-2" type="button" id="yrAttachWorkflow">Continue setup on this page</button>' : ''}`;
    document.getElementById('yrAttachWorkflow')?.addEventListener('click', () => this.startExistingYearWorkflow());
  },

  startExistingYearWorkflow: async function (silent = false) {
    if (this._running || !this._targetYear) return false;
    this._running = true;
    try {
      const result = await callAPI('/academic/year-transition/start-existing-workflow', 'POST', {
        from_year: String(this._currentYear?.year_code || ''),
        to_year: String(this._targetYear.year_code || ''),
      });
      // A newly created workflow returns instance_id/workflow_data. An already
      // active workflow returns the status shape with id/data instead.
      const instanceId = Number(result?.instance_id || result?.id || 0);
      if (!instanceId) throw new Error('The server did not return a rollover workflow ID. Reload the page and try again.');
      this._instanceId = instanceId;
      this._workflowData = result.workflow_data || result.data?.data || result.data || {};
      if (!this._workflowData.academic_year_id && this._targetYear?.id) {
        this._workflowData.academic_year_id = Number(this._targetYear.id);
      }
      this._currentStage = this._canonicalStage(result.current_stage_code || result.current_stage || 'configure_classes_streams');
      localStorage.setItem(this._STORE_KEY, String(instanceId));
      this._log.push({ stage: 'generate_calendar', status: 'completed', details: `Existing ${this._targetYear.year_code} selected and calendar prepared`, performed: new Date().toISOString() });
      if (!silent) showNotification(`Rollover setup for ${this._targetYear.year_code} is ready on this page.`, 'success');
      if (this._instanceId) await this.loadSetupDrafts();
      this._render();
      return true;
    } catch (error) {
      showNotification(error.message || 'Could not continue setup for this academic year.', 'danger');
      return false;
    } finally {
      this._running = false;
    }
  },

  _renderPhaseTabs: function () {
    Object.entries(this._phaseNotes).forEach(([phase, note]) => {
      const el = document.getElementById(`yrPhaseNote-${phase}`);
      if (el) el.textContent = note;
    });
    const tabs = document.querySelectorAll('#yrPhaseTabs [data-yr-phase]');
    tabs.forEach(tab => {
      if (tab.dataset.bound) return;
      tab.dataset.bound = '1';
      tab.addEventListener('click', () => this.navigatePhase(tab.dataset.yrPhase));
    });
    // Open where the workflow IS, never on the last tab looked at: a
    // certified rollover opens on the final tab with what was certified;
    // otherwise the tab of the current stage.
    this._activatePhase(this._landingPhase());
  },

  _isCertified: function () {
    const d = this._workflowData || {};
    return !!(d.certified_at || d.ready_for_new_year);
  },

  _landingPhase: function () {
    if (!this._instanceId) return this._targetYear ? 'classes' : 'year';
    if (this._isCertified()) return 'finance';
    return Object.entries(this._phaseStages).find(([, codes]) => codes.includes(this._currentStage))?.[0]
      || (this._targetYear ? 'classes' : 'year');
  },

  /** The checks that were certified, under the hand-over card. */
  _renderCertification: function () {
    const anchor = document.getElementById('yrHandoverPanel');
    if (!anchor) return;
    let box = document.getElementById('yrCertifiedChecks');
    if (!this._isCertified()) { box?.remove(); return; }
    if (!box) {
      box = document.createElement('div');
      box.id = 'yrCertifiedChecks';
      box.className = 'card border mb-3';
      anchor.insertAdjacentElement('afterend', box);
    }
    const d = this._workflowData || {};
    const checks = d.validation_results || {};
    const label = (k) => k.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
    const items = Object.entries(checks).map(([k, c]) => {
      const ok = (c?.status || '') !== 'fail';
      return `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
          <span>${this._esc(label(k))}</span>
          <span class="${ok ? 'text-success' : 'text-danger'} small fw-semibold"><i class="bi ${ok ? 'bi-check2' : 'bi-x'} me-1"></i>${ok ? 'Passed' : 'Failed'}</span>
        </li>`;
    }).join('');
    const when = d.certified_at ? new Date(String(d.certified_at).replace(' ', 'T')).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }) : '';
    box.innerHTML = `<div class="card-body">
        <h6 class="mb-1"><i class="bi bi-patch-check text-success me-1"></i>${this._esc(d.to_year_code || 'New year')} is certified</h6>
        <p class="small text-muted mb-2">${when ? `Certified ${this._esc(when)}. ` : ''}${d.switch_date ? `The school switches to it on ${this._esc(this._fmtDate(d.switch_date))}.` : 'The school switches to it on its start date.'}</p>
        ${items ? `<ul class="list-group list-group-flush">${items}</ul>` : ''}
      </div>`;
  },

  _activatePhase: function (phase) {
    const phaseIndex = this._phaseOrder.indexOf(phase);
    if (phaseIndex < 0) return;
    this._activePhase = phase;
    try { localStorage.setItem(`${this._PHASE_STORE_KEY}_${this._targetYear?.id || 'no-target'}`, phase); } catch (_) {}
    document.querySelectorAll('#yrPhaseTabs [data-yr-phase]').forEach(tab => {
      const active = tab.dataset.yrPhase === phase;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('#yrPhasePanels [data-yr-phase-panel]').forEach(panel => {
      const active = panel.dataset.yrPhasePanel === phase;
      panel.classList.toggle('active', active);
      panel.classList.toggle('show', active);
    });
    document.querySelectorAll('[data-phase-back]').forEach(button => { button.disabled = phaseIndex <= 0; });
    document.querySelectorAll('[data-phase-next]').forEach(button => { button.disabled = phaseIndex < 0 || phaseIndex >= this._phaseOrder.length - 1; });
  },

  _renderPreflight: function () {
    const s = this._status || {};
    const badge = document.getElementById('yrCurrentYearBadge');
    if (badge) {
      // Every year is active; the sub-state says where it sits.
      const current = s.current_year || null;
      const state = current?.state || 'current';
      badge.textContent = `active - ${state} · ${current?.year_name || this._currentYear?.year_name || '—'}`;
    }

    const setCheck = (iconId, statusId, ok, trueText, falseText) => {
      const icon = document.getElementById(iconId);
      const stat = document.getElementById(statusId);
      if (icon) { icon.style.color = ok ? 'green' : 'red'; icon.className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + ' fs-3 mb-1'; }
      if (stat) stat.textContent = ok ? trueText : falseText;
    };

    const termsIcon = document.getElementById('yrTermsIcon');
    const termsStatus = document.getElementById('yrTermsStatus');
    if (termsIcon) { termsIcon.style.color = '#0d6efd'; termsIcon.className = 'bi bi-calendar-check fs-3 mb-1'; }
    if (termsStatus) termsStatus.textContent = s.all_terms_complete ? 'All Closed' : 'Term 3 Closes at Cutover';

    const resultsIcon = document.getElementById('yrResultsIcon');
    const resultsStatus = document.getElementById('yrResultsStatus');
    if (resultsIcon) { resultsIcon.style.color = '#0d6efd'; resultsIcon.className = 'bi bi-file-earmark-check fs-3 mb-1'; }
    if (resultsStatus) resultsStatus.textContent = (s.pending_results || 0) === 0 ? 'No Learners Flagged' : `${s.pending_results || 0} Learners Need Review`;

    const promotionsIcon = document.getElementById('yrPromotionsIcon');
    const promotionsStatus = document.getElementById('yrPromotionsStatus');
    if (promotionsIcon) { promotionsIcon.style.color = '#0d6efd'; promotionsIcon.className = 'bi bi-person-check fs-3 mb-1'; }
    if (promotionsStatus) promotionsStatus.textContent = (s.pending_promotions || 0) === 0 ? 'Decisions Complete' : `${s.pending_promotions || 0} Decisions Pending`;

    const feesIcon = document.getElementById('yrFeesIcon');
    const feesStatus = document.getElementById('yrFeesStatus');
    if (feesIcon) {
      feesIcon.style.color = (s.students_with_fees || 0) > 0 ? 'orange' : 'green';
      feesIcon.className = 'bi bi-cash-coin fs-3 mb-1';
    }
    if (feesStatus) feesStatus.textContent = (s.students_with_fees || 0) > 0 ? `${s.students_with_fees} students` : 'All Cleared';

  },

  _renderStages: function () {
    this._renderCertification();
    const done = {};
    this._log.forEach(l => { if (l.status === 'completed') done[l.stage] = true; });

    const currentIndex = this._stages.findIndex(s => s.code === this._currentStage);
    Object.entries(this._phaseStages).forEach(([phase, stageCodes]) => {
      const container = document.getElementById(`yrStages-${phase}`);
      if (!container) return;
      const phaseStages = this._stages.filter(stage => stageCodes.includes(stage.code));
      container.innerHTML = phaseStages.map(stage => {
        const index = this._stages.findIndex(item => item.code === stage.code);
        const completed = !!done[stage.code] || (currentIndex >= 0 && index < currentIndex);
        const actionable = !completed && stage.code === this._currentStage;
        return `<div class="list-group-item d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:42px;height:42px;background:var(--bs-${completed ? 'success' : stage.color}-bg-subtle)">
          <i class="bi ${stage.icon} text-${completed ? 'success' : stage.color} fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">${stage.label}</div>
          <div class="text-muted small">${stage.desc}</div>
        </div>
        <div class="flex-shrink-0">
          ${completed
            ? `<span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Done</span>`
            : !actionable
              ? `<span class="badge bg-light text-muted">Waiting</span>`
              : `<button class="btn btn-sm btn-${stage.color}" data-kw-onclick="yearRolloverController.runStage('${stage.code}')"><i class="bi bi-play-fill me-1"></i>${stage.code === 'configure_classes_streams' ? 'Prepare structure' : 'Continue'}</button>`
          }
        </div>
      </div>`;
      }).join('') || '<div class="list-group-item text-muted">No stages in this phase.</div>';
    });
  },

  _renderLog: function () {
    const tbody = document.getElementById('yrLogBody');
    if (!tbody) return;
    if (!this._log.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No transition activity yet.</td></tr>';
      return;
    }
    const colors = { completed: 'success', failed: 'danger', in_progress: 'warning', pending: 'secondary' };
    tbody.innerHTML = this._log.map(l => `<tr>
      <td>${this._esc(l.stage)}</td>
      <td><span class="badge bg-${colors[l.status] || 'secondary'}">${l.status || '—'}</span></td>
      <td class="small">${this._esc(l.details)}</td>
      <td class="small">${l.performed ? new Date(l.performed).toLocaleString('en-KE', { timeZone: 'Africa/Nairobi' }) : '—'}</td>
    </tr>`).join('');
  },

  // ── Stage 1: create new year ──────────────────────────────────────────────
  createNewYear: async function () {
    if (this._running) return;
    const enteredYear = String(document.getElementById('yrToYear')?.value || '').trim();
    const yearMatch = enteredYear.match(/^(\d{4})(?:\/(\d{4}))?$/);
    const yearStart = yearMatch ? Number(yearMatch[1]) : 0;
    const suppliedEndYear = yearMatch?.[2] ? Number(yearMatch[2]) : yearStart + 1;
    const toYear = yearMatch && suppliedEndYear === yearStart + 1
      ? `${yearStart}/${yearStart + 1}`
      : '';
    const startDate = document.getElementById('yrStartDate')?.value;
    const endDate = document.getElementById('yrEndDate')?.value;
    const msg = document.getElementById('yrCreateMsg');

    if (!toYear || !startDate || !endDate) {
      if (msg) msg.textContent = 'Enter a year such as 2027 or 2027/2028, along with start and end dates.';
      showNotification('Enter a valid consecutive year such as 2027 or 2027/2028, along with start and end dates.', 'warning');
      return;
    }
    const terms = [1, 2, 3].map(n => {
      const hasHalfTerm = !!document.getElementById(`yrT${n}HasHalfTerm`)?.checked;
      return {
        term_name: `Term ${n}`,
        start_date: document.getElementById(`yrT${n}Start`)?.value,
        end_date: document.getElementById(`yrT${n}End`)?.value,
        has_half_term: hasHalfTerm,
        half_term_start: hasHalfTerm ? (document.getElementById(`yrT${n}HalfStart`)?.value || null) : null,
        half_term_end: hasHalfTerm ? (document.getElementById(`yrT${n}HalfEnd`)?.value || null) : null,
      };
    });
    if (terms.some(t => !t.start_date || !t.end_date)) {
      showNotification('Please provide start and end dates for all three terms.', 'warning');
      return;
    }
    if (terms.some(t => t.has_half_term && (!t.half_term_start || !t.half_term_end))) {
      showNotification('Provide both half-term dates or leave the half-term option disabled.', 'warning');
      return;
    }
    const dateError = this._validateCalendarDates(yearStart, startDate, endDate, terms);
    if (dateError) {
      if (msg) msg.textContent = dateError;
      showNotification(dateError, 'warning');
      return;
    }

    this._running = true;
    if (msg) msg.textContent = 'Creating new year and generating calendar…';
    try {
      const data = await callAPI('/academic/year-transition/start-workflow', 'POST', {
          from_year: String(this._currentYear?.year_code || ''),
        to_year: toYear,
        year_start_date: startDate,
        year_end_date: endDate,
        terms,
        transition_notes: `Rollover from ${this._currentYear?.year_code} prepared by ${AuthContext.getUser?.().username || 'admin'}`,
      });
      this._instanceId = data.instance_id;
      this._workflowData = data.workflow_data || {};
      this._currentStage = 'configure_classes_streams';
      await this._loadYears();
      this._targetYear = (this._years || []).find(y => String(y.year_code || '') === toYear) || this._targetYear;
      this._activatePhase('classes');
      await this._loadTargetCalendar();
      this._log.push({ stage: 'confirm_current_year', status: 'completed', details: 'Outgoing academic year confirmed', performed: new Date().toISOString() });
      this._log.push({ stage: 'create_next_year', status: 'completed', details: `${toYear} created`, performed: new Date().toISOString() });
      this._log.push({ stage: 'enter_year_term_dates', status: 'completed', details: 'Year and all three term date ranges saved', performed: new Date().toISOString() });
      localStorage.setItem(this._STORE_KEY, String(this._instanceId));
      this._log.push({ stage: 'generate_calendar', status: 'completed', details: `${data.terms_created} terms created · calendar generated`, performed: new Date().toISOString() });
      showNotification(`New academic year ${toYear} created with ${data.terms_created} terms.`, 'success');
      await this._loadStatus();
      await this.loadClassSetupSource();
      this._render();
    } catch (e) {
      if (msg) msg.textContent = '';
      showNotification(e.message || 'Failed to create the new academic year.', 'danger');
    } finally {
      this._running = false;
    }
  },

  _validateCalendarDates: function (targetYear, yearStart, yearEnd, terms) {
    const valid = value => {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
      const [year, month, day] = value.split('-').map(Number);
      const date = new Date(Date.UTC(year, month - 1, day));
      return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
    };
    if (!valid(yearStart) || !valid(yearEnd) || yearStart >= yearEnd) return 'Academic year opening and closing dates must be valid and in order.';
    const startYear = Number(yearStart.slice(0, 4));
    const endYear = Number(yearEnd.slice(0, 4));
    if (startYear !== targetYear || endYear > targetYear + 1) return 'Academic year dates must start in the selected year and end within the following year.';
    let previousEnd = '';
    for (let index = 0; index < terms.length; index++) {
      const term = terms[index];
      if (!valid(term.start_date) || !valid(term.end_date) || term.start_date >= term.end_date) return `Term ${index + 1} opening and closing dates must be valid and in order.`;
      if (term.start_date < yearStart || term.end_date > yearEnd) return `Term ${index + 1} must fall within the academic year dates.`;
      if (previousEnd && term.start_date <= previousEnd) return 'Term dates must be chronological and must not overlap.';
      if (term.has_half_term) {
        if (!valid(term.half_term_start) || !valid(term.half_term_end) || term.half_term_start < term.start_date || term.half_term_end > term.end_date || term.half_term_start > term.half_term_end) return `Term ${index + 1} half-term dates must be valid, ordered and inside the term.`;
      }
      previousEnd = term.end_date;
    }
    return '';
  },

  // ── Stages 2-6 ────────────────────────────────────────────────────────────
  runStage: async function (code, askConfirm = true, followWorkflow = true) {
    if (this._running) return false;
    const stage = this._stages.find(s => s.code === code);
    const confirmMsg = code === 'configure_classes_streams'
      ? 'Prepare the selected target-year classes and streams using the values in the Classes & Streams tab? This also copies annual learning-area and fee setup as editable target-year drafts.'
      : `Run stage: "${stage?.label || code}"?\n\nThis action modifies database records.`;
    if (askConfirm && !await window.confirmAction('Confirm', confirmMsg)) return false;

    this._running = true;
    this._setRunning(code, true);
    try {
      let data = null;
      if (code === 'configure_classes_streams') {
        const streamOverrides = {};
        const capacityOverrides = {};
        const learnerTypesByClass = {};
        for (const row of this._classDraftRows) {
          const targetClassId = Number(row.target_class_id || 0);
          if (!targetClassId) continue;
          const names = String(row.stream_names || '').split(',').map(name => name.trim()).filter(Boolean);
          if (!names.length) throw new Error(`Enter at least one target stream for ${row.target_class_name || row.source_class_name}.`);
          const capacity = Number(row.capacity);
          if (!Number.isInteger(capacity) || capacity < 1) throw new Error(`Enter a positive stream capacity for ${row.target_class_name || row.source_class_name}.`);
          streamOverrides[targetClassId] = names;
          capacityOverrides[targetClassId] = capacity;
          learnerTypesByClass[targetClassId] = Array.isArray(row.learner_type_codes) ? row.learner_type_codes : ['DAY'];
        }
        data = await callAPI('/academic/year-transition/setup-new-year', 'POST', {
          instance_id: this._instanceId,
          from_year: String(this._workflowData?.from_year_code || this._workflowData?.from_year || this._currentYear?.year_code || ''),
          capacity: 40,
          default_ecd_streams: 1,
          stream_overrides: streamOverrides,
          capacity_overrides: capacityOverrides,
          learner_types_by_class: learnerTypesByClass,
        });
        const local = this._readLocalEdits();
        delete local.classes;
        try { localStorage.setItem(this._draftStorageKey(), JSON.stringify(local)); } catch (_) {}
        this._localSetupEdits = local;
        const localSaveState = document.getElementById('yrClassDraftSaveState');
        if (localSaveState) localSaveState.textContent = 'Class and stream setup saved to the target academic year.';
        const classStageIndex = this._stages.findIndex(item => item.code === 'configure_classes_streams');
        const currentStageIndex = this._stages.findIndex(item => item.code === this._currentStage);
        if (currentStageIndex < 0 || currentStageIndex <= classStageIndex) {
          this._moveTo('configure_learning_areas');
        }
        this._workflowData = {
          ...(this._workflowData || {}),
          academic_year_id: data.academic_year_id || this._workflowData?.academic_year_id,
          new_classes: data.new_classes || this._workflowData?.new_classes || [],
          class_learner_types: data.learner_types_by_class || this._workflowData?.class_learner_types || {},
          new_year_context: data.context || data.new_year_context || {},
        };
        await this.loadClassSetupSource();
        await this.loadSetupDrafts();
        const la = data.learning_area_setup || {};
        this._log.push({ stage: code, status: 'completed', details: `${data.classes_created} classes · ${(la.classes || []).length} classes with learning areas`, performed: new Date().toISOString() });
        showNotification(`Target-year setup prepared: ${data.classes_created} classes and copied context.`, 'success');
      } else if (['configure_learning_areas', 'configure_teachers', 'prepare_fee_structures', 'approve_fee_structures'].includes(code)) {
        data = await callAPI('/academic/year-transition/complete-stage', 'POST', {
          instance_id: this._instanceId,
          stage_code: code,
        });
        if (data.current_stage) this._moveTo(this._canonicalStage(data.current_stage));
        const financeDetails = data.finance_rollover?.[code] || data[code] || {};
        this._log.push({ stage: code, status: 'completed', details: financeDetails.learners_recorded != null
          ? `${financeDetails.learners_recorded} learner balances recorded.`
          : financeDetails.learners_reconciled != null ? `${financeDetails.learners_reconciled} learner balances reconciled.`
            : financeDetails.fee_bundles_processed != null ? `${financeDetails.fee_bundles_processed} fee bundles processed.`
              : 'Stage completed and progress saved.', performed: new Date().toISOString() });
        showNotification(`${stage?.label || code} completed.`, 'success');
      } else if (code === 'close_outgoing_year') {
        // The outgoing year becomes active - completed: readable by every user,
        // writable by school admin / system admin / director or a user granted
        // academic_years_edit_completed.
        data = await callAPI('/academic/year-transition/close-year', 'POST', {
          instance_id: this._instanceId,
        });
        this._moveTo('activate_new_year_term_one');
        this._log.push({
          stage: code, status: 'completed',
          details: `Outgoing year closed as active - completed · assessments, attendance and competencies retained and readable`,
          performed: new Date().toISOString(),
        });
        showNotification('Outgoing year closed. Its records stay readable for every user.', 'success');
      } else if (['review_promotion_candidates', 'assign_promotion_decisions', 'assign_target_streams'].includes(code)) {
        await this.loadPromotionBoard();
        this._running = false;
        this._setRunning(code, false);
        return;
      } else if (code === 'migrate_baselines') {
        data = await callAPI('/academic/year-transition/migrate-competency-baselines', 'POST', {
          instance_id: this._instanceId,
        });
        this._moveTo('close_outgoing_year');
        this._log.push({
          stage: 'migrate_baselines', status: 'completed',
          details: `${data.total_baselines ?? 0} baselines · ${data.students_tracked ?? 0} students · ${data.competencies_tracked ?? 0} competencies`,
          performed: new Date().toISOString(),
        });
        showNotification(`Migrated ${data.total_baselines ?? 0} competency baselines.`, 'success');
      } else if (code === 'activate_new_year_term_one') {
        await this._validateAndActivate();
        await this._loadStatus();
        this._running = false;
        this._setRunning(code, false);
        this._render();
        return true;
      }
      if (followWorkflow) await this._followWorkflowStage();
      this._renderStages();
      this._renderLog();
      return true;
    } catch (e) {
      // Readiness validation can legitimately fail on partial prep — show the
      // detailed results instead of a bare error.
      if (code === 'activate_new_year_term_one' && e.response?.data?.validation_results) {
        this._log.push({
          stage: 'validate_readiness', status: 'failed',
          details: this._validationSummary(e.response.data.validation_results),
          performed: new Date().toISOString(),
        });
      } else {
        this._log.push({ stage: code, status: 'failed', details: e.message || 'Stage failed', performed: new Date().toISOString() });
      }
      showNotification(e.message || `Stage "${code}" failed.`, 'danger');
      this._renderLog();
      return false;
    } finally {
      this._running = false;
      this._setRunning(code, false);
    }
  },

  _populatePromotionStreams: function (classSelectId, streamSelectId) {
    const classSelect = document.getElementById(classSelectId);
    const streamSelect = document.getElementById(streamSelectId);
    if (!streamSelect) return;
    const classId = Number(classSelect?.value || 0);
    const selected = streamSelect.value;
    const streams = new Map();
    (this._promotionCandidates || []).forEach(candidate => {
      if (classId && Number(candidate.source_class_id) !== classId) return;
      streams.set(String(candidate.source_stream_id), String(candidate.source_stream_name || ''));
    });
    const allLabel = classSelectId === 'yrBulkSourceClass' ? 'All streams' : 'All streams';
    streamSelect.innerHTML = `<option value="">${allLabel}</option>` + [...streams.entries()]
      .sort((a, b) => a[1].localeCompare(b[1]))
      .map(([id, name]) => `<option value="${this._esc(id)}">${this._esc(name)}</option>`).join('');
    if ([...streams.keys()].includes(selected)) streamSelect.value = selected;
  },

  _renderPromotionRuleBuilder: function () {
    const classes = new Map();
    (this._promotionCandidates || []).forEach(candidate => classes.set(String(candidate.source_class_id), String(candidate.source_class_name || '')));
    const classOptions = [...classes.entries()].sort((a, b) => a[1].localeCompare(b[1]))
      .map(([id, name]) => `<option value="${this._esc(id)}">${this._esc(name)}</option>`).join('');
    ['yrBulkSourceClass', 'yrPromotionClassFilter'].forEach(id => {
      const select = document.getElementById(id);
      if (!select) return;
      const current = select.value;
      select.innerHTML = `<option value="">${id === 'yrBulkSourceClass' ? 'Choose class' : 'All classes'}</option>${classOptions}`;
      if ([...classes.keys()].includes(current)) select.value = current;
    });
    this._populatePromotionStreams('yrBulkSourceClass', 'yrBulkSourceStream');
    this._populatePromotionStreams('yrPromotionClassFilter', 'yrPromotionStreamFilter');
    this._populateBulkTargets();
    this._renderPromotionRules();
  },

  _populateBulkTargets: function () {
    const select = document.getElementById('yrBulkTarget');
    const decision = document.getElementById('yrBulkDecision')?.value || 'promote';
    if (!select) return;
    const sourceClassId = Number(document.getElementById('yrBulkSourceClass')?.value || 0);
    const sourceStreamId = Number(document.getElementById('yrBulkSourceStream')?.value || 0);
    const destinations = new Map();
    if (decision !== 'graduate') (this._promotionCandidates || []).forEach(candidate => {
      if (sourceClassId && Number(candidate.source_class_id) !== sourceClassId) return;
      if (sourceStreamId && Number(candidate.source_stream_id) !== sourceStreamId) return;
      const choices = decision === 'retain' ? candidate.retention_streams : candidate.target_streams;
      (choices || []).forEach(stream => destinations.set(String(stream.target_stream_id), `${decision === 'retain' ? candidate.source_class_name : candidate.target_class_name} / ${stream.stream_name}`));
    });
    select.innerHTML = decision === 'graduate'
      ? '<option value="0">Graduate from school</option>'
      : '<option value="">Choose destination</option>' + [...destinations.entries()].sort((a, b) => a[1].localeCompare(b[1]))
        .map(([id, label]) => `<option value="${this._esc(id)}">${this._esc(label)}</option>`).join('');
    select.disabled = decision === 'graduate';
  },

  _renderPromotionRules: function () {
    const body = document.getElementById('yrPromotionRulesBody');
    if (!body) return;
    body.innerHTML = (this._promotionRules || []).map((rule, index) => {
      const className = (this._promotionCandidates || []).find(candidate => Number(candidate.source_class_id) === Number(rule.source_class_id))?.source_class_name || 'Class';
      const streamName = rule.source_stream_id
        ? ((this._promotionCandidates || []).find(candidate => Number(candidate.source_stream_id) === Number(rule.source_stream_id))?.source_stream_name || 'Stream')
        : 'All streams';
      const target = rule.decision === 'graduate' ? 'Graduation' : rule.target_label;
      const count = (this._promotionCandidates || []).filter(candidate => Number(candidate.source_class_id) === Number(rule.source_class_id)
        && (!Number(rule.source_stream_id) || Number(candidate.source_stream_id) === Number(rule.source_stream_id))).length;
      return `<tr><td>${this._esc(className)} / ${this._esc(streamName)}</td><td>${this._esc(rule.decision)}</td><td>${this._esc(target || '—')}</td><td>${count}</td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-promotion-rule="${index}" aria-label="Remove mapping"><i class="bi bi-trash"></i></button></td></tr>`;
    }).join('') || '<tr><td colspan="5" class="text-muted">No bulk mappings added.</td></tr>';
    const summary = document.getElementById('yrPromotionRuleSummary');
    if (summary) summary.textContent = `${(this._promotionRules || []).length} mappings · apply to matching learners, then review any skipped placements.`;
  },

  addPromotionRule: function () {
    const sourceClassId = Number(document.getElementById('yrBulkSourceClass')?.value || 0);
    const sourceStreamId = Number(document.getElementById('yrBulkSourceStream')?.value || 0);
    const decision = document.getElementById('yrBulkDecision')?.value || 'promote';
    const targetSelect = document.getElementById('yrBulkTarget');
    const targetStreamId = Number(targetSelect?.value || 0);
    if (!sourceClassId || (decision !== 'graduate' && !targetStreamId)) {
      showNotification('Choose a current class and a valid target placement.', 'warning');
      return;
    }
    const option = targetSelect?.selectedOptions?.[0];
    this._promotionRules.push({ source_class_id: sourceClassId, source_stream_id: sourceStreamId, decision, target_stream_id: targetStreamId, target_label: option?.textContent || 'Graduation' });
    this._renderPromotionRules();
    this.savePhaseDraft('promotions', true);
  },

  applyPromotionRules: async function () {
    let queued = 0;
    let skipped = 0;
    (this._promotionRules || []).forEach(rule => (this._promotionCandidates || []).forEach(candidate => {
      if (Number(candidate.source_class_id) !== Number(rule.source_class_id)) return;
      if (Number(rule.source_stream_id) && Number(candidate.source_stream_id) !== Number(rule.source_stream_id)) return;
      const row = document.querySelector(`#yrPromotionBoardBody tr[data-student-id="${CSS.escape(String(candidate.student_id))}"]`);
      const decision = row?.querySelector('.yr-promotion-decision');
      const target = row?.querySelector('.yr-target-stream');
      const selected = row?.querySelector('.yr-promotion-select');
      if (!row || !decision || !target || !selected) return;
      if (rule.decision === 'graduate') {
        if (Number(candidate.target_class_id || 0)) { skipped++; return; }
        decision.value = 'graduate';
        target.value = '0';
      } else {
        const choices = rule.decision === 'retain' ? candidate.retention_streams : candidate.target_streams;
        if (!(choices || []).some(stream => Number(stream.target_stream_id) === Number(rule.target_stream_id))) { skipped++; return; }
        decision.value = rule.decision;
        this._filterPromotionTargetOptions(row);
        target.value = String(rule.target_stream_id);
      }
      selected.checked = true;
      queued++;
    }));
    this._updatePromotionSelectionCount();
    if (!queued) {
      showNotification(skipped ? `${skipped} learner(s) do not have a compatible destination in this target structure.` : 'No learners matched the mappings.', 'warning');
      return false;
    }
    showNotification(`${queued} learner placements queued${skipped ? ` · ${skipped} skipped for review` : ''}. Saving now.`, skipped ? 'warning' : 'info');
    return this.savePromotionAssignments();
  },

  _filterPromotionTargetOptions: function (row) {
    const target = row?.querySelector('.yr-target-stream');
    const decision = row?.querySelector('.yr-promotion-decision')?.value || 'promote';
    if (!target) return;
    target.querySelectorAll('option[data-decision]').forEach(option => { option.hidden = option.dataset.decision !== decision; });
    target.disabled = decision === 'graduate';
    if (decision === 'graduate') target.value = '0';
  },

  _filterPromotionRows: function () {
    const search = String(document.getElementById('yrPromotionSearch')?.value || '').trim().toLowerCase();
    const classId = String(document.getElementById('yrPromotionClassFilter')?.value || '');
    const streamId = String(document.getElementById('yrPromotionStreamFilter')?.value || '');
    const status = document.getElementById('yrPromotionStatusFilter')?.value || 'all';
    document.querySelectorAll('#yrPromotionBoardBody tr[data-student-id]').forEach(row => {
      const matchesSearch = !search || (row.dataset.search || '').includes(search);
      const matchesClass = !classId || row.dataset.sourceClassId === classId;
      const matchesStream = !streamId || row.dataset.sourceStreamId === streamId;
      const matchesStatus = status === 'all' || row.dataset.assigned === (status === 'assigned' ? '1' : '0');
      row.hidden = !(matchesSearch && matchesClass && matchesStream && matchesStatus);
    });
    const selectVisible = document.getElementById('yrPromotionSelectVisible');
    if (selectVisible) selectVisible.checked = false;
  },

  _updatePromotionSelectionCount: function () {
    const selected = document.querySelectorAll('#yrPromotionBoardBody .yr-promotion-select:checked').length;
    const count = document.getElementById('yrPromotionSelectionCount');
    if (count) count.textContent = `${selected} selected`;
  },

  loadPromotionBoard: async function () {
    const res = await callAPI('/academic/year-transition/promotion-candidates?instance_id=' + encodeURIComponent(this._instanceId), 'GET');
    this._promotionCandidates = res.candidates || [];
    this._promotionBoardLoaded = true;
    const board = document.getElementById('yrPromotionBoard');
    if (board) board.classList.remove('d-none');
    const assigned = res.assigned || 0;
    const total = res.total || 0;
    const progress = document.getElementById('yrPromotionProgress');
    if (progress) progress.textContent = `${assigned} of ${total} learners assigned`;
    const body = document.getElementById('yrPromotionBoardBody');
    if (!body) return;
    body.innerHTML = this._promotionCandidates.map(c => {
      const promotionOptions = (c.target_streams || []).map(s => `<option data-decision="promote" value="${this._esc(s.target_stream_id)}">${this._esc(c.target_class_name)} / ${this._esc(s.stream_name)}</option>`).join('');
      const retentionOptions = (c.retention_streams || []).map(s => `<option data-decision="retain" value="${this._esc(s.target_stream_id)}">${this._esc(c.source_class_name)} / ${this._esc(s.stream_name)}</option>`).join('');
      const graduateOption = !c.target_class_id ? '<option data-decision="graduate" value="0">Graduate</option>' : '';
      const missingTerms = Number(c.finalized_result_terms ?? 0) < 3;
      const balance = Number(c.outstanding_balance || 0);
      const assigned = !!c.assigned;
      const search = `${c.student_name || ''} ${c.admission_no || ''}`.toLowerCase();
      return `<tr data-student-id="${this._esc(c.student_id)}" data-source-class-id="${this._esc(c.source_class_id)}" data-source-stream-id="${this._esc(c.source_stream_id)}" data-assigned="${assigned ? '1' : '0'}" data-search="${this._esc(search)}">
        <td><input type="checkbox" class="form-check-input yr-promotion-select" aria-label="Select ${this._esc(c.student_name)}"></td>
        <td><div class="fw-semibold">${this._esc(c.student_name)}</div><div class="small text-muted">${this._esc(c.admission_no)}</div></td>
        <td>${this._esc(c.source_class_name)} / ${this._esc(c.source_stream_name)}</td>
        <td>${missingTerms ? `<span class="badge bg-warning text-dark">Results need review (${this._esc(c.finalized_result_terms ?? 0)}/3 terms)</span><div class="small text-muted">Promotion remains the administrator’s decision.</div>` : '<span class="badge bg-success">Results recorded</span>'}</td>
        <td>${balance > 0 ? `<span class="text-warning-emphasis">KES ${this._esc(balance.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</span><div class="small text-muted">Carried in finance stage</div>` : 'KES 0.00'}</td>
        <td><select class="form-select form-select-sm yr-promotion-decision"><option value="promote">Promote</option><option value="retain">Retain in class</option>${!c.target_class_id ? '<option value="graduate">Graduate</option>' : ''}</select></td>
        <td><select class="form-select form-select-sm yr-target-stream"><option value="">Select placement…</option>${promotionOptions}${retentionOptions}${graduateOption}</select></td>
        <td>${this._promotionUnavailableIds.has(Number(c.student_id)) ? '<span class="badge bg-danger">Needs adjustment</span><div class="small text-muted">Enable this learner type in Classes & Streams, or choose a compatible class.</div>' : c.assigned ? '<span class="badge bg-success">Assigned</span>' : '<span class="badge bg-warning text-dark">Pending</span>'}</td>
      </tr>`;
    }).join('') || '<tr><td colspan="8" class="text-center text-muted">No learners require a decision.</td></tr>';
    this._promotionCandidates.forEach(c => {
      const row = body.querySelector(`tr[data-student-id="${CSS.escape(String(c.student_id))}"]`);
      const select = row?.querySelector('.yr-target-stream');
      const decision = row?.querySelector('.yr-promotion-decision');
      if (select && c.transition_type === 'graduation' && decision) {
        decision.value = 'graduate';
        select.value = '0';
      } else if (select && c.target_enrollment_id) {
        const retained = Number(c.existing_target_class_id || c.target_class_id) === Number(c.source_class_id);
        if (decision) decision.value = retained ? 'retain' : 'promote';
        select.value = String(c.target_stream_id || '');
      }
      this._filterPromotionTargetOptions(row);
    });
    this._renderPromotionRuleBuilder();
    this._restorePhaseDraft('promotions');
    this._filterPromotionRows();
    this._updatePromotionSelectionCount();
  },

  savePromotionAssignments: async function () {
    if (this._running) return false;
    const assignments = [];
    document.querySelectorAll('#yrPromotionBoardBody tr[data-student-id]').forEach(row => {
      if (!row.querySelector('.yr-promotion-select')?.checked) return;
      const select = row.querySelector('.yr-target-stream');
      const decision = row.querySelector('.yr-promotion-decision');
      const action = decision?.value || 'promote';
      if (select && (action === 'graduate' || Number(select.value) > 0)) assignments.push({ student_id: Number(row.dataset.studentId), decision: action, target_stream_id: action === 'graduate' ? 0 : Number(select.value) });
    });
    if (!assignments.length) {
      const nextPhase = this._phaseForStage(this._currentStage);
      if (nextPhase && nextPhase !== 'promotions' && this._phaseOrder.indexOf(nextPhase) > this._phaseOrder.indexOf('promotions')) {
        await this.navigatePhase(nextPhase);
        return true;
      }
      showNotification('Select learners and choose their decisions before saving.', 'warning');
      return false;
    }
    const assignedIds = new Set(assignments.map(a => String(a.student_id)));
    this._running = true;
    try {
      const res = await callAPI('/academic/year-transition/assign-promotion-streams', 'POST', { instance_id: this._instanceId, assignments });
      const unavailableIds = new Set(this._promotionUnavailableIds);
      assignedIds.forEach(id => unavailableIds.delete(Number(id)));
      (res.unavailable_student_ids || []).forEach(id => unavailableIds.add(Number(id)));
      this._promotionUnavailableIds = unavailableIds;
      const localDrafts = this._readLocalEdits();
      if (localDrafts.__phase_snapshots) delete localDrafts.__phase_snapshots.promotions;
      try { localStorage.setItem(this._draftStorageKey(), JSON.stringify(localDrafts)); } catch (_) {}
      this._localSetupEdits = localDrafts;
      if (res.unavailable) {
        showNotification(`${res.unavailable} learner(s) remain pending. Adjust their learner type availability in Classes & Streams or choose a compatible class, then save again. Other valid assignments were saved.`, 'warning');
      } else {
        showNotification(res.complete ? 'All promotion assignments are complete.' : 'Assignments saved. You can continue later.', 'success');
      }
      this._log.push({
        stage: 'assign_target_streams',
        status: res.complete ? 'completed' : 'in_progress',
        details: [
          `${res.saved || assignments.length} saved`,
          `${res.unassigned || 0} remaining`,
          `${res.learners_billed || 0} billed into the target year`,
          res.arrears_carried ? `KES ${(res.arrears_carried || 0).toLocaleString('en-KE')} arrears carried` : null,
          res.credits_carried ? `KES ${(res.credits_carried || 0).toLocaleString('en-KE')} credits applied` : null,
          res.graduates_balance_retained ? `${res.graduates_balance_retained} graduate balance(s) retained` : null,
          res.unavailable ? ` · ${res.unavailable} unavailable` : null,
        ].filter(Boolean).join(' · '),
        performed: new Date().toISOString()
      });
      if (res.complete) this._moveTo('migrate_baselines');
      await this.loadPromotionBoard();
      this._renderStages();
      this._renderLog();
      if (res.complete) await this.navigatePhase('finance');
      return true;
    } catch (e) { showNotification(e.message || 'Could not save promotion assignments.', 'danger'); return false; }
    finally { this._running = false; }
  },

  _validateAndActivate: async function () {
    const res = await callAPI('/academic/year-transition/validate-readiness', 'POST', {
      instance_id: this._instanceId,
    });
    const ready = !!res.ready_for_new_year;
    const checks = res.validation_results || {};
    if (!ready) {
      const err = new Error('New academic year is not ready yet');
      err.response = { data: { validation_results: checks } };
      throw err;
    }

    // Certify, do not switch. The outgoing year keeps serving the school until
    // its last day; AcademicActivationService::sweep() performs the cutover when
    // the target year opens, so the switch can never land mid-term.
    const switchDate = res.switch_date || this._workflowData?.to_year_start_date || null;
    const switchLabel = this._fmtDate(switchDate);
    this._workflowData = { ...(this._workflowData || {}), certified_at: res.certified_at, switch_date: switchDate };

    this._log.push({
      stage: 'activate_new_year_term_one', status: 'completed',
      details: `All checks passed · certified · automatic switch on ${switchLabel}`,
      performed: new Date().toISOString(),
    });
    if (window.AcademicContext) await window.AcademicContext.refresh();
    if (window.DataStore) window.DataStore.invalidateMany(['academic', 'attendance', 'finance']).catch(() => {});
    showNotification(
      switchDate
        ? `New academic year certified. The system switches automatically on ${switchLabel}.`
        : 'New academic year certified. The system switches automatically on its start date.',
      'success'
    );

    // Hand back to the rollover view: the workflow stays in_progress so the
    // handover panel keeps reporting the countdown and the readiness checks.
    await this._loadHandover();
    this._renderStages();
    this._renderLog();
  },

  _validationSummary: function (checks) {
    const fails = [];
    for (const [key, check] of Object.entries(checks || {})) {
      if (check && check.status === 'fail') {
        fails.push(key.replace(/_/g, ' '));
      }
    }
    return fails.length ? 'Not ready — fix: ' + fails.join(', ') : 'All checks passed';
  },

  _setRunning: function (code, running) {
    document.querySelectorAll('#yrStages .btn').forEach(b => { if (b && !b.disabled) b.disabled = running; });
  },

  _importWorkflowLog: function () {
    const d = this._workflowData || {};
    // Closing the outgoing year is recorded by the workflow stage pointer
    // (workflow_stage_history), not by any payload on the instance.
    if (d.promotion_summary) {
      const complete = d.promotion_summary.assignment_complete === true;
      this._log.push({ stage: 'assign_target_streams', status: complete ? 'completed' : 'in_progress', details: complete ? 'All learner streams assigned' : `${d.promotion_summary.unassigned ?? 'Some'} learners still require stream assignment`, performed: null });
    }
    if (d.new_classes && d.new_classes.length) this._log.push({ stage: 'configure_classes_streams', status: 'completed', details: `${d.new_classes.length} classes`, performed: null });
    if (d.migrated_baselines) this._log.push({ stage: 'migrate_baselines', status: 'completed', details: 'Previously migrated', performed: null });
    if (d.ready_for_new_year) this._log.push({ stage: 'activate_new_year_term_one', status: 'completed', details: 'Already validated', performed: null });
  },

  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => window.yearRolloverController.init());
