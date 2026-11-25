/**
 * Term Transition Controller
 *
 * The page opens with a live handover verdict, not a blindly rendered wizard.
 * It reads the current period, term records, active year-transition workflow
 * and workflow status before deciding whether a current/next term pair really
 * exists. The only official write remains the explicit activation call.
 */
const termTransitionController = {
  state: {
    context: null,
    terms: [],
    years: [],
    period: null,
    transition: null,
    workflow: null,
    handover: null,
    currentTerm: null,
    nextTerm: null,
    resultReadiness: null,
    lessonPlanSummary: null,
    timetable: { slots: [], classCount: 0, teacherCount: 0 },
    rollover: { enabled: true, keepTeachers: true, keepRooms: true },
    step: 1,
    eventsBound: false,
    writeAllowed: false,
    busy: false,
  },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  // ── Small DOM/date helpers ────────────────────────────────────────────────
  node: (id) => document.getElementById(id),

  text(id, value) {
    const node = this.node(id);
    if (node) node.textContent = String(value ?? '');
  },

  day(value) {
    return String(value || '').slice(0, 10);
  },

  todayISO() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const date = String(now.getDate()).padStart(2, '0');
    return `${now.getFullYear()}-${month}-${date}`;
  },

  plainDate(value) {
    const raw = this.day(value);
    if (!raw) return null;
    if (window.Temporal?.PlainDate) {
      try {
        return window.Temporal.PlainDate.from(raw);
      } catch (_) {
        // Fall through to a local-calendar Date.
      }
    }
    const [year, month, date] = raw.split('-').map(Number);
    if (!year || !month || !date) return null;
    return new Date(year, month - 1, date);
  },

  fmtDate(value) {
    const raw = this.day(value);
    const date = this.plainDate(raw);
    if (!date) return raw ? String(value) : '—';
    try {
      return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
    } catch (_) {
      return raw;
    }
  },

  fmtShortDate(value) {
    const raw = this.day(value);
    const date = this.plainDate(raw);
    if (!date) return raw ? String(value) : '—';
    try {
      return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' }).format(date);
    } catch (_) {
      return raw;
    }
  },

  /**
   * callAPI already returns the response data. Only unwrap a real transport
   * envelope; a domain record that legitimately has a nested `data` object
   * (such as the year-transition workflow) must stay intact.
   */
  payload(response) {
    let value = response;
    for (let depth = 0; depth < 3; depth += 1) {
      if (value == null || typeof value !== 'object') return null;
      if (value.status === 'error' || value.status === 'fail') return null;
      if (Array.isArray(value)) return value;
      const responseStatus = String(value.status || '').toLowerCase();
      const isResponseStatus = ['success', 'ok', 'error', 'fail'].includes(responseStatus);
      const isEnvelope = Object.hasOwn(value, 'data')
        && (isResponseStatus
          || value.success === true
          || Object.hasOwn(value, 'message')
          || Object.hasOwn(value, 'code')
          || Object.keys(value).length === 1);
      if (!isEnvelope) return value;
      value = value.data;
    }
    return value;
  },

  settledValue(result) {
    return result?.status === 'fulfilled' ? this.payload(result.value) : null;
  },

  listFrom(response) {
    let value = response;
    for (let depth = 0; depth < 6; depth += 1) {
      if (Array.isArray(value)) return value;
      if (!value || typeof value !== 'object') return null;
      value = value.terms ?? value.academic_terms ?? value.years ?? value.academic_years
        ?? value.data ?? value.items ?? value.rows ?? value.records;
    }
    return Array.isArray(value) ? value : null;
  },

  statusOf(term) {
    const status = String(term?.status || term?.term_status || term?.term_period_status || '').trim().toLowerCase();
    if (term?.is_current === true || Number(term?.is_current) === 1 || status === 'current' || status === 'active') return 'current';
    if (status === 'completed' || status === 'closed') return 'completed';
    if (status === 'upcoming' || status === 'planned') return 'upcoming';
    return status;
  },

  termDates(term) {
    return {
      start: this.day(term?.start_date || term?.opening_date),
      end: this.day(term?.end_date || term?.closing_date),
    };
  },

  termYearId(term) {
    return String(term?.academic_year_id ?? term?.year_id ?? term?.year ?? '');
  },

  termName(term) {
    return String(term?.name || term?.term_name || 'Term');
  },

  yearState(year) {
    const state = String(year?.state || '').trim().toLowerCase();
    if (state) return state;
    if (year?.is_current === true || Number(year?.is_current) === 1) return 'current';
    return String(year?.status || year?.year_status || '').trim().toLowerCase();
  },

  orderedYears(years) {
    return years.toSorted((a, b) => String(a.start_date || '').localeCompare(String(b.start_date || '')));
  },

  findCurrentYear(years) {
    return years.find((year) => this.yearState(year) === 'current') || years[0] || null;
  },

  findNextYear(years, currentYear) {
    if (!currentYear) return null;
    const ordered = this.orderedYears(years);
    const index = ordered.findIndex((year) => String(year.id) === String(currentYear.id));
    return ordered[index + 1] || years.find((year) => this.yearState(year) === 'upcoming') || null;
  },

  yearCode(year) {
    return String(year?.year_code || year?.code || year?.name || year?.year_name || '');
  },

  yearLabel(year) {
    return String(year?.year_name || year?.name || year?.year_code || 'the academic year');
  },

  fallbackTargetYearCode() {
    const current = this.findCurrentYear(this.state.years);
    const match = String(this.yearCode(current)).match(/(\d{4})\s*\/\s*\d{4}/);
    const start = match ? Number(match[1]) : 0;
    return start > 0 ? `${start}/${start + 1}` : '';
  },

  stageLabel(value) {
    const stage = String(value || '').trim();
    if (!stage) return '—';
    const labels = {
      close_outgoing_year: 'Close outgoing year',
      activate_new_year_term_one: 'Activate incoming Term 1',
      prepare_calendar: 'Prepare calendar',
      setup_classes: 'Set up classes',
      promote_learners: 'Promote learners',
      validate_readiness: 'Validate readiness',
    };
    return labels[stage] || stage.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
  },

  homeLink(route) {
    return `${window.APP_BASE || ''}/home.php?route=${route}`;
  },

  // ── Pure state derivation ─────────────────────────────────────────────────
  /**
   * Convert backend state into one plain-language handover decision. This is
   * read-only: no status is inferred into the database and no wizard is shown
   * just because the page expects one.
   */
  deriveHandover({
    terms = [],
    years = [],
    period = null,
    transition = null,
    workflow = null,
    currentTerm = null,
    nextTerm = null,
    today = this.todayISO(),
  } = {}) {
    const currentYear = this.findCurrentYear(years);
    const nextYear = this.findNextYear(years, currentYear);
    const currentYearId = String(currentYear?.id ?? '');
    const yearTerms = currentYearId
      ? terms.filter((term) => this.termYearId(term) === currentYearId)
      : [];
    const allComplete = yearTerms.length > 0 && yearTerms.every((term) => this.statusOf(term) === 'completed');

    const active = transition || {};
    const status = workflow || {};
    const activeData = active.data && typeof active.data === 'object' ? active.data : active;
    const statusData = status.data && typeof status.data === 'object' ? status.data : status;
    const details = { ...activeData, ...statusData };
    const workflowData = (transition?.data && typeof transition.data === 'object') ? transition.data : activeData;
    const switchDate = this.day(details.switch_date || workflowData.switch_date);
    const certified = Boolean(details.ready_for_new_year || workflowData.ready_for_new_year) && Boolean(switchDate);
    const workflowStatus = String(details.status || active.status || '');
    const stage = String(details.current_stage || details.stage_code || active.current_stage || '');
    const inProgress = Boolean(transition && (workflowStatus === 'in_progress' || stage));
    const targetCode = String(details.to_year_code || this.yearCode(nextYear) || '');

    const from = currentTerm || terms.find((term) => this.statusOf(term) === 'current') || null;
    const to = nextTerm || (from
      ? terms.find((term) => this.statusOf(term) === 'upcoming'
          && Number(term.id) > Number(from.id)
          && this.termYearId(term) === this.termYearId(from))
      : null);

    const recent = this.findTermById(period?.current_period?.after_term_id, terms)
      || this.recentTerm(terms, today);
    const incoming = this.findTermById(
      period?.planning_term?.academic_year_term_id || period?.next_period?.academic_year_term_id,
      terms,
    ) || this.incomingTerm(terms, today);

    const common = {
      allComplete,
      currentYear,
      nextYear,
      currentTerm: from,
      nextTerm: to,
      recent,
      incoming,
      switchDate,
      certified,
      stage,
      targetCode,
      wizard: Boolean(from && to && !allComplete),
      linkLabel: '',
      linkHref: this.homeLink('year_rollover'),
    };

    if (allComplete && certified && switchDate > today) {
      return {
        ...common,
        key: 'waiting',
        eyebrow: 'Automatic cutover scheduled',
        title: 'Waiting for the automatic switch',
        detail: `All ${yearTerms.length} terms in ${this.yearLabel(currentYear)} are complete. ${targetCode ? `${targetCode} takes over` : 'The new year takes over'} on ${this.fmtDate(switchDate)}. No term action is due — the system will switch on that date.`,
        badge: 'Auto switch scheduled',
        linkLabel: 'Review year rollover',
      };
    }
    if (allComplete && certified && switchDate && switchDate <= today) {
      return {
        ...common,
        key: 'due',
        eyebrow: 'Cutover date reached',
        title: 'Check the automatic switch',
        detail: `The certified switch date was ${this.fmtDate(switchDate)}. Confirm that ${targetCode || 'the incoming year'} is active before editing the old year.`,
        badge: 'Check cutover',
        linkLabel: 'Check year rollover',
      };
    }
    if (allComplete && inProgress) {
      return {
        ...common,
        key: 'in-progress',
        eyebrow: 'Year transition in progress',
        title: 'Finish the year transition checks',
        detail: `All terms in ${this.yearLabel(currentYear)} are complete, but the automatic switch is not certified yet. Continue the checklist to schedule the cutover date.`,
        badge: 'Transition in progress',
        linkLabel: 'Continue transition',
      };
    }
    if (allComplete) {
      return {
        ...common,
        key: 'schedule',
        eyebrow: 'End of academic year',
        title: 'Schedule the year transition',
        detail: `Every term in ${this.yearLabel(currentYear)} is complete and no certified switch is waiting. Open Year Rollover to prepare ${targetCode || 'the next year'} and set the cutover date.`,
        badge: 'Action needed',
        linkLabel: 'Schedule transition',
      };
    }

    const current = period?.current_period || null;
    const kind = current?.kind || '';
    const periodDetail = current
      ? `${current.label || current.name || 'This period'} runs until ${this.fmtDate(current.end_date)}.`
      : '';

    if (kind === 'holiday' || kind === 'half_term') {
      const isHoliday = kind === 'holiday';
      return {
        ...common,
        key: isHoliday ? 'holiday' : 'half-term',
        eyebrow: isHoliday ? 'School holiday' : 'Mid-term break',
        title: `${current.name || (isHoliday ? 'School holiday' : 'Mid-term break')} — handover view`,
        detail: `${periodDetail} The register keeps ${this.termName(recent)} in view${incoming ? ` and shows ${this.termName(incoming)} as the next term` : ''}. No term transition is due during the break.`,
        badge: isHoliday ? 'School holiday' : 'Mid-term break',
        linkLabel: 'Review term dates',
        linkHref: this.homeLink('manage_terms'),
      };
    }
    if (kind === 'term') {
      return {
        ...common,
        key: 'term',
        eyebrow: 'Term in session',
        title: `${current.name || this.termName(from)} is in session`,
        detail: `${periodDetail} ${to ? `The handover below can move the school into ${this.termName(to)} when records are ready.` : 'No paired upcoming term is configured for this handover.'}`,
        badge: 'Term in session',
        linkLabel: '',
      };
    }
    if (from && to) {
      return {
        ...common,
        key: 'ready',
        eyebrow: 'Term pair ready',
        title: `Hand over from ${this.termName(from)} to ${this.termName(to)}`,
        detail: `The live register has a current term and a configured successor. Work through the handover below; the official status changes only at final activation.`,
        badge: 'Pair ready',
        linkLabel: '',
      };
    }
    return {
      ...common,
      key: 'unknown',
      eyebrow: 'Calendar status',
      title: 'No term handover available',
      detail: 'There is no current term paired with an upcoming term. New years and terms are prepared in the year workflow; this page only moves an already configured pair.',
      badge: 'No pair available',
      linkLabel: 'Open year rollover',
    };
  },

  findTermById(id, terms) {
    if (!id) return null;
    return terms.find((term) => String(term.id) === String(id)) || null;
  },

  recentTerm(terms, today) {
    const closed = terms
      .filter((term) => this.statusOf(term) === 'completed' || (this.termDates(term).end && this.termDates(term).end <= today))
      .sort((a, b) => this.termDates(b).end.localeCompare(this.termDates(a).end));
    return closed[0] || terms.toSorted((a, b) => this.termDates(b).end.localeCompare(this.termDates(a).end))[0] || null;
  },

  incomingTerm(terms, today) {
    return terms
      .filter((term) => this.statusOf(term) === 'upcoming' || (this.termDates(term).start && this.termDates(term).start > today))
      .sort((a, b) => this.termDates(a).start.localeCompare(this.termDates(b).start))[0] || null;
  },

  // ── Init and loading ──────────────────────────────────────────────────────
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    if (window.AuthContext?.isAuthenticated?.() === false) {
      window.location.href = `${window.APP_BASE || ''}/index.php`;
      return;
    }
    this.applyPermissions();
    this.bindEvents();
    await this.load();

    window.APIRealtime?.register?.('term_transition', {
      refresh: async () => {
        await this.load();
      },
    }, ['academic']);
  },

  applyPermissions() {
    const auth = window.AuthContext;
    const canWrite = ['academic_year_manage', 'system_admin', 'academic.manage', 'academic_year.manage']
      .some((code) => auth?.hasPermission?.(code));
    this.state.writeAllowed = canWrite;
    document.querySelectorAll('#ttWizard [data-year-write]').forEach((button) => {
      button.disabled = !canWrite;
      if (!canWrite) button.title = 'Your account cannot write academic years.';
    });
    const print = this.node('ttPrintBtn');
    if (print && auth?.canPrint && !auth.canPrint('academic')) {
      print.disabled = true;
      print.title = 'Your account cannot print academic data.';
    }
    const foot = document.querySelector('.tt-aside-foot');
    if (foot && !canWrite) foot.textContent = 'You can review the handover, but only an authorised academic-year administrator can activate it.';
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;

    this.node('ttPrintBtn')?.addEventListener('click', () => this.print());
    this.node('ttProceedBtn')?.addEventListener('click', () => this.goStep(2));
    this.node('ttBackStep1Btn')?.addEventListener('click', () => this.goStep(1));
    this.node('ttCloseTermBtn')?.addEventListener('click', () => this.confirmClosure());
    this.node('ttBackStep2Btn')?.addEventListener('click', () => this.goStep(2));
    this.node('ttSkipRolloverBtn')?.addEventListener('click', () => this.skipRollover());
    this.node('ttBackStep3Btn')?.addEventListener('click', () => this.goStep(3));
    this.node('ttBackStep4Btn')?.addEventListener('click', () => this.goStep(4));
    this.node('ttActivateBtn')?.addEventListener('click', () => this.requestActivation());

    this.node('ttRolloverForm')?.addEventListener('submit', (event) => {
      event.preventDefault();
      this.submitRollover();
    });
    this.node('ttSetupForm')?.addEventListener('submit', (event) => {
      event.preventDefault();
      this.saveLocalSetup();
    });

    document.querySelectorAll('[data-error]').forEach((field) => {
      field.addEventListener('input', () => this.syncField(field));
      field.addEventListener('change', () => this.syncField(field));
      field.addEventListener('blur', () => this.syncField(field));
    });

    const dialog = this.node('ttConfirmDialog');
    dialog?.addEventListener('close', () => {
      const confirmed = dialog.returnValue === 'confirm';
      dialog.returnValue = '';
      if (confirmed) void this.executeTransition();
    });
  },

  async load() {
    try {
      const [contextResult, yearsResult, periodResult] = await Promise.allSettled([
        this.API('GET', '/api/academic/term-transition/context'),
        this.API('GET', '/api/academic/years/list'),
        this.API('GET', '/api/academic/current-period'),
      ]);

      const context = this.settledValue(contextResult);
      const years = this.listFrom(yearsResult?.status === 'fulfilled' ? yearsResult.value : null);
      this.state.context = context && typeof context === 'object' ? context : {};
      this.state.terms = Array.isArray(this.state.context.terms) ? this.state.context.terms : [];
      this.state.years = years?.length ? years : this.yearsFromTerms(this.state.terms);
      this.state.period = this.settledValue(periodResult);
      this.state.resultReadiness = this.state.context.result_readiness || null;
      this.state.lessonPlanSummary = this.state.context.lesson_plan_summary || null;
      this.state.currentTerm = this.state.context.current_term
        || this.state.terms.find((term) => this.statusOf(term) === 'current') || null;
      this.state.nextTerm = this.state.context.next_term
        || (this.state.currentTerm
          ? this.state.terms.find((term) => this.statusOf(term) === 'upcoming'
              && Number(term.id) > Number(this.state.currentTerm.id)
              && this.termYearId(term) === this.termYearId(this.state.currentTerm))
          : null);

      await this.loadActiveWorkflow();
      this.renderHandover();
    } catch (error) {
      console.error('Term transition setup failed:', error);
      this.renderErrorState();
    }
  },

  yearsFromTerms(terms) {
    const seen = new Map();
    terms.forEach((term) => {
      const id = this.termYearId(term);
      if (!id || seen.has(id)) return;
      seen.set(id, {
        id,
        year_code: term.year_code || term.academic_year_name || term.year_name || '',
        name: term.year_name || term.academic_year_name || term.year_code || '',
        start_date: this.termDates(term).start,
        status: this.statusOf(term) === 'current' ? 'current' : '',
      });
    });
    return [...seen.values()];
  },

  async loadActiveWorkflow() {
    this.state.transition = null;
    this.state.workflow = null;
    const currentYear = this.findCurrentYear(this.state.years);
    const nextYear = this.findNextYear(this.state.years, currentYear);
    const targetYear = this.yearCode(nextYear) || this.fallbackTargetYearCode();
    if (!targetYear) return;

    const response = await this.API('GET', '/api/academic/year-transition/active', null, { to_year: targetYear });
    this.state.transition = this.payload(response);
    const instanceId = this.state.transition?.id;
    if (!instanceId) return;

    const statusResponse = await this.API('GET', '/api/academic/workflow/status', null, {
      workflow_type: 'year-transition',
      instance_id: instanceId,
    });
    this.state.workflow = this.payload(statusResponse);
  },

  // ── Rendering ─────────────────────────────────────────────────────────────
  renderErrorState() {
    const panel = this.node('ttStatePanel');
    if (panel) panel.dataset.state = 'error';
    const badge = this.node('ttCurrentTermBadge');
    if (badge) {
      badge.dataset.state = 'error';
      badge.textContent = 'Check connection';
    }
    this.text('ttStateEyebrow', 'Handover unavailable');
    this.text('ttStateTitle', 'Could not read the handover');
    this.text('ttStateDetail', 'Refresh the page. No term status has been changed.');
    const link = this.node('ttStateLink');
    if (link) link.hidden = true;
  },

  renderHandover() {
    const handover = this.deriveHandover({
      terms: this.state.terms,
      years: this.state.years,
      period: this.state.period,
      transition: this.state.transition,
      workflow: this.state.workflow,
      currentTerm: this.state.currentTerm,
      nextTerm: this.state.nextTerm,
    });
    this.state.handover = handover;
    this.renderState(handover);
    this.renderLedger(handover);

    const wizard = this.node('ttWizard');
    if (wizard) wizard.hidden = !handover.wizard;
    if (handover.wizard) {
      this.applyPermissions();
      this.renderWizard();
      void this.loadTimetableStats();
    }
    this.announce(`${handover.title}. ${handover.detail}`);
  },

  renderState(handover) {
    const panel = this.node('ttStatePanel');
    const badge = this.node('ttCurrentTermBadge');
    if (panel) panel.dataset.state = handover.key;
    if (badge) {
      badge.dataset.state = handover.key;
      badge.textContent = handover.badge;
    }
    this.text('ttStateEyebrow', handover.eyebrow);
    this.text('ttStateTitle', handover.title);
    this.text('ttStateDetail', handover.detail);

    this.text('ttStateFrom', handover.recent ? this.termName(handover.recent) : '—');
    this.text('ttStateTo', handover.incoming ? this.termName(handover.incoming) : '—');
    this.setDateFact('ttStateSwitch', handover.switchDate);
    this.text('ttStateStage', handover.stage ? this.stageLabel(handover.stage) : '—');

    const link = this.node('ttStateLink');
    if (link) {
      link.hidden = !handover.linkLabel;
      link.href = handover.linkHref || this.homeLink('year_rollover');
      this.text('ttStateLinkLabel', handover.linkLabel || '');
    }

    const note = handover.certified
      ? `Certified handover · switch on ${this.fmtDate(handover.switchDate)} · stage ${this.stageLabel(handover.stage)}`
      : handover.key === 'holiday' || handover.key === 'half-term'
        ? 'The register keeps the last completed term and the next configured term in view.'
        : `Current decision · ${handover.badge}`;
    this.text('ttHandoverNote', note);
  },

  setDateFact(id, value) {
    const node = this.node(id);
    if (!node) return;
    node.replaceChildren();
    if (!value) {
      node.textContent = '—';
      return;
    }
    const time = document.createElement('time');
    time.dateTime = value;
    time.textContent = this.fmtDate(value);
    node.append(time);
  },

  renderLedger(handover) {
    this.renderLedgerEntry('ttRecentEntry', 'ttRecentName', 'ttRecentDates', handover.recent, 'Recently completed');
    this.renderLedgerEntry('ttIncomingEntry', 'ttIncomingName', 'ttIncomingDates', handover.incoming, 'Next in line');
  },

  renderLedgerEntry(entryId, nameId, datesId, term, fallbackLabel) {
    const entry = this.node(entryId);
    if (entry) entry.classList.toggle('is-empty', !term);
    this.text(nameId, term ? this.termName(term) : fallbackLabel);
    const dates = this.node(datesId);
    if (!dates) return;
    dates.replaceChildren();
    if (!term) {
      dates.textContent = 'No term is configured here yet.';
      return;
    }
    const range = this.termDates(term);
    ['start', 'end'].forEach((key) => {
      if (!range[key]) return;
      const time = document.createElement('time');
      time.dateTime = range[key];
      time.textContent = this.fmtShortDate(range[key]);
      dates.append(time);
    });
    if (dates.childNodes.length === 2) dates.insertBefore(document.createTextNode(' – '), dates.childNodes[1]);
    if (!dates.childNodes.length) dates.textContent = 'Dates not set';
  },

  renderWizard() {
    const from = this.state.currentTerm;
    const to = this.state.nextTerm;
    if (!from || !to) return;

    this.text('ttStepLbl1', `Review ${this.termName(from)}`);
    this.text('ttStep1Name', this.termName(from));
    this.text('ttCurrentTermName', this.termName(from));
    this.text('ttConfirmTermName', this.termName(from));
    this.text('ttStepLbl4', `Set ${this.termName(to)} dates`);
    this.text('ttNextTermHeader', this.termName(to));
    this.text('ttNextTermStartLabel', `${this.termName(to)} opening date`);
    this.text('ttNextTermEndLabel', `${this.termName(to)} closing date`);
    this.text('ttNextTermChecklistTitle', `${this.termName(to)} checklist`);
    this.text('ttActivateHeader', this.termName(to));
    this.text('ttDoneTitle', `${this.termName(to)} is now active`);

    const start = this.node('ttTerm2Start');
    const end = this.node('ttTerm2End');
    const halfStart = this.node('ttMidtermStart');
    const halfEnd = this.node('ttMidtermEnd');
    if (start) start.value = this.day(to.start_date);
    if (end) end.value = this.day(to.end_date);
    if (halfStart) halfStart.value = this.day(to.half_term_start);
    if (halfEnd) halfEnd.value = this.day(to.half_term_end);

    this.renderReview();
    this.renderResultReadiness();
    this.renderActivateSummaries();
    this.goStep(1);
  },

  fillLedger(id, entries) {
    const target = this.node(id);
    if (!target) return;
    target.replaceChildren();
    entries.forEach(([label, value]) => {
      const row = document.createElement('div');
      const dt = document.createElement('dt');
      dt.textContent = label;
      const dd = document.createElement('dd');
      dd.textContent = String(value ?? '—');
      row.append(dt, dd);
      target.append(row);
    });
  },

  fillChecklist(id, entries) {
    const target = this.node(id);
    if (!target) return;
    target.replaceChildren();
    entries.forEach(({ label, done, detail }) => {
      const item = document.createElement('li');
      item.dataset.done = done ? 'true' : 'false';
      const mark = document.createElement('span');
      mark.className = 'tt-mark';
      mark.setAttribute('aria-hidden', 'true');
      mark.textContent = done ? '✓' : '!';
      const copy = document.createElement('span');
      copy.textContent = label;
      if (detail) copy.append(document.createElement('br'), document.createTextNode(detail));
      item.append(mark, copy);
      target.append(item);
    });
  },

  renderReview() {
    const from = this.state.currentTerm;
    const to = this.state.nextTerm;
    const slots = this.state.timetable.slots.length;
    const approved = Number(this.state.lessonPlanSummary?.approved || 0);
    const pending = Number(this.state.lessonPlanSummary?.pending || 0);
    const dates = this.termDates(from);

    this.fillLedger('ttReviewStats', [
      ['Outgoing term', this.termName(from)],
      ['Opens', this.fmtDate(dates.start)],
      ['Closes', this.fmtDate(dates.end)],
      ['Timetable slots', slots],
    ]);
    this.fillChecklist('ttCompletedList', [
      { label: 'Outgoing term is current', done: this.statusOf(from) === 'current' },
      { label: `Lesson plans approved (${approved})`, done: approved > 0 },
      { label: `${this.termName(to)} dates configured`, done: Boolean(this.day(to.start_date) && this.day(to.end_date)) },
    ]);
    this.fillChecklist('ttPendingList', [
      { label: 'Timetable configured', done: slots > 0, detail: slots > 0 ? '' : 'No live timetable slots were found.' },
      { label: 'Lesson plans in review', done: pending === 0, detail: pending > 0 ? `${pending} still draft or submitted.` : '' },
    ]);
  },

  renderResultReadiness() {
    const target = this.node('ttResultsReadiness');
    if (!target) return;
    target.replaceChildren();
    const readiness = this.state.resultReadiness;
    target.hidden = !readiness || readiness.ready;
    if (!readiness || readiness.ready) return;

    const title = document.createElement('strong');
    title.textContent = 'Close the assessment cycle first';
    const detail = document.createElement('p');
    const parts = [];
    if (Number(readiness.pending_registers) > 0) parts.push(`${readiness.pending_registers} learning-area register(s) awaiting approval`);
    if (Number(readiness.pending_learners) > 0) parts.push(`${readiness.pending_learners} learner result(s) awaiting approval`);
    detail.textContent = parts.length
      ? `${parts.join(' and ')}. Unapproved marks never reach the official term results.`
      : readiness.message || 'Some assessment evidence is still open.';
    const label = document.createElement('label');
    label.htmlFor = 'ttForceReason';
    label.textContent = 'Reason if continuing without approved results';
    const input = document.createElement('input');
    input.type = 'text';
    input.id = 'ttForceReason';
    input.maxLength = 255;
    input.placeholder = 'Required only for a genuinely empty results cycle';
    input.setAttribute('data-error', 'ttForceReasonError');
    input.setAttribute('aria-invalid', 'false');
    const error = document.createElement('p');
    error.className = 'tt-field-error';
    error.id = 'ttForceReasonError';
    error.hidden = true;
    error.textContent = 'Record why this term has no approved results before continuing.';
    target.append(title, detail, label, input, error);
    input.addEventListener('input', () => this.syncField(input));
  },

  renderActivateSummaries() {
    const from = this.state.currentTerm;
    const to = this.state.nextTerm;
    const dates = this.termDates(to);
    const entries = [
      ['Outgoing', this.termName(from)],
      ['Incoming', this.termName(to)],
      ['Opens', this.fmtDate(dates.start)],
      ['Closes', this.fmtDate(dates.end)],
      ['Timetable', this.state.rollover.enabled ? `Copy ${this.state.timetable.slots.length} slots` : 'Not copied'],
    ];
    this.fillLedger('ttActivateSummary', entries);
    this.fillLedger('ttConfirmSummary', entries.slice(0, 4));
  },

  async loadTimetableStats() {
    const term = this.state.currentTerm;
    if (!term) return;
    try {
      const response = await this.API('GET', '/api/academic/timetable-stats', null, { term_id: term.id });
      const payload = this.payload(response) || {};
      const slots = Array.isArray(payload.slots) ? payload.slots : [];
      this.state.timetable = {
        slots,
        classCount: Number(payload.class_count ?? new Set(slots.map((slot) => slot.class_id)).size),
        teacherCount: Number(payload.teacher_count ?? new Set(slots.map((slot) => slot.teacher_id).filter(Boolean)).size),
      };
      this.text('ttSlotCount', String(this.state.timetable.slots.length));
      this.text('ttClassCount', String(this.state.timetable.classCount));
      this.text('ttTeacherCount', String(this.state.timetable.teacherCount));
      this.text('ttRolloverStatus', 'Ready for atomic activation');
      this.renderReview();
      this.renderActivateSummaries();
    } catch (error) {
      console.warn('Timetable stats were not available:', error);
      this.text('ttRolloverStatus', 'Unavailable — activation can still proceed');
    }
  },

  // ── Wizard steps (local preparation only) ─────────────────────────────────
  goStep(step) {
    const target = step === 'done' ? 'ttDone' : `ttStep${step}`;
    ['ttStep1', 'ttStep2', 'ttStep3', 'ttStep4', 'ttStep5', 'ttDone'].forEach((id) => {
      const panel = this.node(id);
      if (panel) panel.hidden = id !== target;
    });
    const wizard = this.node('ttWizard');
    if (wizard) wizard.hidden = false;
    const progress = document.querySelector('.tt-progress');
    if (progress) progress.hidden = step === 'done';

    const numeric = Number(step) || 0;
    document.querySelectorAll('.tt-step').forEach((item) => {
      const value = Number(item.dataset.step) || 0;
      if (value === numeric) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
      item.classList.toggle('is-complete', value < numeric);
    });

    this.state.step = step;
    const heading = this.node(target)?.querySelector('h2');
    if (heading) {
      heading.focus?.({ preventScroll: true });
      heading.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
    }
    if (step === 4) this.renderActivateSummaries();
  },

  syncField(field) {
    if (field?.getAttribute('aria-invalid') !== 'true') return;
    const value = field.type === 'checkbox' ? field.checked : String(field.value || '').trim();
    if (value) this.clearFieldError(field);
  },

  setFieldError(field, invalid) {
    if (!field) return;
    const errorId = field.dataset.error;
    const error = errorId ? this.node(errorId) : null;
    if (invalid) {
      field.setAttribute('aria-invalid', 'true');
      field.classList.add('user-invalid-fallback');
      if (error) error.hidden = false;
      if (errorId) {
        const describedBy = String(field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (!describedBy.includes(errorId)) describedBy.push(errorId);
        field.setAttribute('aria-describedby', describedBy.join(' '));
      }
      return;
    }
    field.setAttribute('aria-invalid', 'false');
    field.classList.remove('user-invalid-fallback');
    if (error) error.hidden = true;
    if (errorId) {
      const describedBy = String(field.getAttribute('aria-describedby') || '').split(/\s+/)
        .filter((token) => token && token !== errorId);
      if (describedBy.length) field.setAttribute('aria-describedby', describedBy.join(' '));
      else field.removeAttribute('aria-describedby');
    }
  },

  clearFieldError(field) {
    this.setFieldError(field, false);
  },

  showStepError(id, message) {
    const error = this.node(id);
    if (error) {
      error.textContent = message;
      error.hidden = false;
    }
    this.announce(message);
  },

  clearStepError(id) {
    const error = this.node(id);
    if (error) {
      error.textContent = '';
      error.hidden = true;
    }
  },

  confirmClosure() {
    const checkbox = this.node('ttConfirmClose');
    this.clearStepError('ttCloseError');
    if (!checkbox?.checked) {
      this.setFieldError(checkbox, true);
      this.showStepError('ttCloseError', 'Confirm that the outgoing term is finalised before continuing.');
      checkbox?.focus();
      return;
    }
    this.clearFieldError(checkbox);
    // Local preparation only. No status write until final activation.
    if (this.state.currentTerm) this.state.currentTerm.status = 'pending_close';
    this.notify('Closure prepared. No official status has been written yet.', 'info');
    this.goStep(3);
  },

  submitRollover() {
    this.clearStepError('ttRolloverError');
    this.state.rollover = {
      enabled: true,
      keepTeachers: this.node('ttKeepTeachers')?.checked ?? true,
      keepRooms: this.node('ttKeepRooms')?.checked ?? true,
    };
    this.text('ttRolloverStatus', 'Selected for atomic activation');
    this.renderActivateSummaries();
    this.notify('Timetable copy will run with activation, not now.', 'info');
    this.goStep(4);
  },

  skipRollover() {
    this.state.rollover = { enabled: false, keepTeachers: false, keepRooms: false };
    this.text('ttRolloverStatus', 'Skipped');
    this.renderActivateSummaries();
    this.goStep(4);
  },

  saveLocalSetup() {
    const startField = this.node('ttTerm2Start');
    const endField = this.node('ttTerm2End');
    const halfStartField = this.node('ttMidtermStart');
    const halfEndField = this.node('ttMidtermEnd');
    const start = this.day(startField?.value);
    const end = this.day(endField?.value);
    const halfStart = this.day(halfStartField?.value);
    const halfEnd = this.day(halfEndField?.value);
    const invalid = [];
    const reasons = [];

    [startField, endField, halfStartField, halfEndField].forEach((field) => this.clearFieldError(field));
    this.clearStepError('ttSetupError');

    if (!start) {
      invalid.push(startField);
      reasons.push('Choose an opening date.');
    }
    if (!end) {
      invalid.push(endField);
      reasons.push('Choose a closing date.');
    } else if (start && end <= start) {
      invalid.push(endField);
      reasons.push('The closing date must be after the opening date.');
    }
    if (Boolean(halfStart) !== Boolean(halfEnd)) {
      invalid.push(halfStart ? halfEndField : halfStartField);
      reasons.push('Enter both mid-term dates, or leave both blank.');
    } else if (halfStart && halfEnd) {
      if (halfEnd < halfStart || (start && halfStart < start) || (end && halfEnd > end)) {
        invalid.push(halfStartField, halfEndField);
        reasons.push('Mid-term dates must sit inside the term, in order.');
      }
    }
    if (invalid.length) {
      invalid.forEach((field) => this.setFieldError(field, true));
      this.showStepError('ttSetupError', reasons.join(' '));
      invalid[0]?.focus?.();
      return;
    }

    // Local preview only; these dates are committed atomically at activation.
    if (this.state.nextTerm) {
      this.state.nextTerm.start_date = start;
      this.state.nextTerm.end_date = end;
    }
    this.renderActivateSummaries();
    this.notify('Dates prepared. No official record has been changed yet.', 'info');
    this.goStep(5);
  },

  requestActivation() {
    if (!this.state.writeAllowed) {
      this.showStepError('ttActivateError', 'Your account cannot activate an academic-year handover.');
      return;
    }
    if (!this.state.currentTerm || !this.state.nextTerm) {
      this.showStepError('ttActivateError', 'The current and incoming terms must both be available.');
      return;
    }
    const readiness = this.state.resultReadiness;
    if (readiness && !readiness.ready) {
      const reason = this.node('ttForceReason');
      if (!String(reason?.value || '').trim()) {
        this.setFieldError(reason, true);
        this.showStepError('ttActivateError', 'Record why the results cycle can be closed without approved marks.');
        reason?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
        reason?.focus?.();
        return;
      }
    }
    this.clearStepError('ttActivateError');
    const dialog = this.node('ttConfirmDialog');
    if (dialog?.showModal) dialog.showModal();
    else if (window.confirmAction) void this.executeTransition();
  },

  async executeTransition() {
    if (this.state.busy) return;
    const from = this.state.currentTerm;
    const to = this.state.nextTerm;
    const button = this.node('ttActivateBtn');
    this.state.busy = true;
    if (button) button.disabled = true;

    const halfStart = this.day(this.node('ttMidtermStart')?.value);
    const halfEnd = this.day(this.node('ttMidtermEnd')?.value);
    const forceReason = String(this.node('ttForceReason')?.value || '').trim();

    try {
      await this.API('POST', '/api/academic/term-transition/execute', {
        from_term_id: from.id,
        to_term_id: to.id,
        academic_year_id: this.termYearId(to),
        rollover_timetable: this.state.rollover.enabled,
        keep_teachers: this.state.rollover.keepTeachers,
        keep_rooms: this.state.rollover.keepRooms,
        ...(forceReason ? { force_reason: forceReason } : {}),
        dates: {
          start_date: this.day(to.start_date),
          end_date: this.day(to.end_date),
          half_term_start: halfStart || null,
          half_term_end: halfEnd || null,
        },
      });
      await window.AcademicContext?.refresh?.();
      window.DataStore?.invalidateMany?.(['academic', 'timetable', 'attendance']);
      this.notify(`${this.termName(to)} is now active.`, 'success');
      await this.load();
      this.goStep('done');
    } catch (error) {
      this.showStepError('ttActivateError', error?.message || 'Activation failed. No partial change was kept.');
    } finally {
      this.state.busy = false;
      if (button) button.disabled = !this.state.writeAllowed;
    }
  },

  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
      this.notify('You do not have permission to print academic data.', 'warning');
      return;
    }
    const handover = this.state.handover;
    const context = this.node('ttPrintContext');
    if (context) {
      context.textContent = `Term Transition · ${handover?.title || 'Handover sheet'} · ${this.fmtDate(this.todayISO())}`;
    }
    window.print?.();
  },

  announce(message) {
    const node = this.node('ttAnnouncement');
    if (!node) return;
    if (window.requestAnimationFrame) {
      node.textContent = '';
      window.requestAnimationFrame(() => {
        node.textContent = message;
      });
      return;
    }
    node.textContent = message;
  },

  notify(message, type = 'info') {
    if (window.showNotification) window.showNotification(message, type);
    else if (window.showToast) window.showToast(message, type);
    else console.info(message);
  },
};

window.termTransitionController = termTransitionController;
window.TermTransitionController = termTransitionController;
document.addEventListener('DOMContentLoaded', () => termTransitionController.init().catch((error) => {
  console.error('Term transition page initialization failed:', error);
  termTransitionController.renderErrorState();
}));
