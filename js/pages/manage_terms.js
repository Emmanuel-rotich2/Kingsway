/**
 * Manage Terms Controller
 *
 * The register is filter-driven: changing a filter redraws the term sequence,
 * metrics and table immediately. Years and terms are still created by Year
 * Transition; this page edits dates and status through the existing API.
 */
const manageTermsController = {
  state: {
    referenceCache: null,
    terms: [],
    years: [],
    period: null,
    transition: null,
    phase: null,
    sort: { column: 3, direction: 'ascending' },
    eventsBound: false,
  },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
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

  /** API calls are enveloped; unwrap once without treating a list as a record. */
  payload(response) {
    let value = response;
    for (let depth = 0; depth < 4; depth += 1) {
      if (value == null || typeof value !== 'object') return null;
      if (value.status === 'error' || value.status === 'fail') return null;
      if (Array.isArray(value)) return value;
      if (Object.hasOwn(value, 'data')) {
        value = value.data;
        continue;
      }
      return value;
    }
    return value;
  },

  settledValue(result) {
    return result?.status === 'fulfilled' ? this.payload(result.value) : null;
  },

  /** Calendar dates are location-agnostic; prefer Temporal PlainDate where available. */
  plainDate(value) {
    const raw = this.day(value);
    if (!raw) return null;
    if (window.Temporal?.PlainDate) {
      try {
        return window.Temporal.PlainDate.from(raw);
      } catch (_) {
        // Fall through to the local-calendar Date below.
      }
    }
    const [year, month, date] = raw.split('-').map(Number);
    if (!year || !month || !date) return null;
    return new Date(year, month - 1, date);
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

  yearState(year) {
    const state = String(year?.state || '').trim().toLowerCase();
    if (state) return state;
    if (year?.is_current === true || Number(year?.is_current) === 1) return 'current';
    return String(year?.status || year?.year_status || '').trim().toLowerCase();
  },

  currentYear() {
    return this.state.years.find((year) => this.yearState(year) === 'current') || this.state.years[0] || null;
  },

  yearName(year) {
    return String(year?.year_name || year?.name || year?.year_code || '—');
  },

  yearFilterValue() {
    const value = document.getElementById('yearFilter')?.value || '';
    if (value === 'all') return 'all';
    return value || String(this.currentYear()?.id || '');
  },

  /** Terms in the chosen year only; search/status/date filters come later. */
  scopeTerms() {
    const yearId = this.yearFilterValue();
    if (yearId === 'all' || !yearId) return [...this.state.terms];
    return this.state.terms.filter((term) => {
      const rawYearId = term.academic_year_id ?? term.year_id ?? term.year;
      return String(rawYearId) === String(yearId);
    });
  },

  scopeLabel() {
    const yearId = this.yearFilterValue();
    if (yearId === 'all') return 'all academic years';
    const selected = this.state.years.find((year) => String(year.id) === String(yearId));
    return selected ? this.yearName(selected) : 'the selected academic year';
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read('academic', 'academic-reports-references');
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn('Manage Terms Node view failed; using direct reads:', error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [terms, years] = await Promise.all([
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms'),
        cache?.years !== undefined ? cache.years : this.API('GET', '/api/academic/years/list'),
      ]);
      const normalizedTerms = this.listFrom(terms);
      const normalizedYears = this.listFrom(years);
      if (!normalizedTerms || !normalizedYears) {
        throw new Error('The academic API returned an unexpected terms or years data structure.');
      }
      this.state.terms = normalizedTerms;
      this.state.years = normalizedYears;
      this.renderYearFilterOptions();
      this.renderFormOptions();
      this.bindEvents();
      this.readUrlState();
      this.renderTerms();
      void this.loadCalendarContext();
    } catch (error) {
      console.error('Failed to load terms:', error);
      ['mtStatTotal', 'mtStatActive', 'mtStatCompleted', 'mtStatUpcoming'].forEach((id) => {
        const node = document.getElementById(id);
        if (node) node.textContent = '0';
      });
      const overview = document.getElementById('mtOverviewYear');
      if (overview) overview.textContent = 'Term register';
      this.renderTermBand([]);
      const count = document.getElementById('mtResultCount');
      if (count) count.textContent = 'Not available';
      this.showTermsState('Could not load terms. Check your connection and refresh the page.', 'mt-empty--error');
    }
  },

  filteredTerms() {
    const status = document.getElementById('filterSelect')?.value || '';
    const search = String(document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const date = this.day(document.getElementById('dateFilter')?.value);
    return this.scopeTerms().filter((term) => {
      const matchesStatus = !status || this.statusOf(term) === status;
      const haystack = `${term.name || term.term_name || ''} ${term.year_name || term.academic_year_name || ''} ${term.year_code || ''}`.toLowerCase();
      const matchesSearch = !search || haystack.includes(search);
      const { start, end } = this.termDates(term);
      const matchesDate = !date || (start && end && start <= date && date <= end);
      return matchesStatus && matchesSearch && matchesDate;
    });
  },

  hasFilters() {
    const yearId = this.yearFilterValue();
    return Boolean(
      (document.getElementById('searchInput')?.value || '').trim()
      || document.getElementById('filterSelect')?.value
      || document.getElementById('dateFilter')?.value
      || (yearId && yearId !== String(this.currentYear()?.id || ''))
    );
  },

  renderTerms() {
    const terms = this.filteredTerms();
    const body = document.getElementById('mtTermsBody');
    const stateRow = document.getElementById('mtTermsState');
    const template = document.getElementById('mtTermRowTemplate');
    if (!body || !stateRow || !template) return;

    const scoped = this.scopeTerms();
    this.renderStats(scoped);
    this.renderTermBand(scoped);
    this.renderResultCount(terms.length);
    this.syncUrl();
    this.applyYearMode();

    body.querySelectorAll('[data-term-row]').forEach((row) => row.remove());
    if (!terms.length) {
      this.showTermsState(
        this.state.terms.length ? 'No terms match these filters.' : 'No terms are recorded. New years are prepared in Year Transition.',
        '',
        this.hasFilters(),
      );
      this.syncSortIndicators();
      this.announce('No terms shown.');
      return;
    }

    stateRow.hidden = true;
    const fragment = document.createDocumentFragment();
    terms.forEach((term, index) => {
      const row = template.content.cloneNode(true);
      const dates = this.termDates(term);
      const name = term.name || term.term_name || 'Term';
      const year = term.year_name || term.academic_year_name || term.year_code || term.year || '—';
      const weeks = Number(term.weeks ?? term.week_count ?? 0);
      const status = this.statusOf(term);
      const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Not set';

      const setCell = (key, value, raw) => {
        const cell = row.querySelector(`[data-fill="${key}"]`);
        if (!cell) return;
        cell.textContent = value;
        if (raw !== undefined) cell.dataset.value = String(raw);
      };
      const setDateCell = (key, raw) => {
        const cell = row.querySelector(`[data-fill="${key}"]`);
        if (!cell) return;
        cell.replaceChildren();
        if (!raw) {
          cell.textContent = '—';
          cell.dataset.value = '';
          return;
        }
        const time = document.createElement('time');
        time.dateTime = raw;
        time.textContent = this.fmtDate(raw);
        cell.append(time);
        cell.dataset.value = raw;
      };
      setCell('number', String(index + 1), index + 1);
      row.querySelector('[data-fill="name"]').textContent = name;
      setCell('year', String(year), String(year));
      setDateCell('start', dates.start);
      setDateCell('end', dates.end);
      setCell('weeks', weeks > 0 ? String(weeks) : '—', weeks || 0);

      const badge = row.querySelector('[data-fill="status"]');
      badge.textContent = label;
      badge.className = `mt-status mt-status--${this.esc(status || 'upcoming')}`;
      badge.dataset.value = label;
      const statusCell = badge.closest('td');
      if (statusCell) statusCell.dataset.value = label;

      const edit = row.querySelector('[data-action="edit"]');
      edit.dataset.termId = String(Number(term.id));
      edit.setAttribute('aria-label', `Edit ${name}`);
      fragment.appendChild(row);
    });
    body.insertBefore(fragment, stateRow);
    this.applySort();
    this.syncSortIndicators();
    this.announce(`${terms.length} academic ${terms.length === 1 ? 'term' : 'terms'} shown.`);
  },

  showTermsState(message, tone = '', clearable = false) {
    const body = document.getElementById('mtTermsBody');
    const stateRow = document.getElementById('mtTermsState');
    body?.querySelectorAll('[data-term-row]').forEach((row) => row.remove());
    const cell = stateRow?.querySelector('td');
    if (cell) {
      cell.replaceChildren();
      cell.className = `mt-empty ${tone}`.trim();
      const paragraph = document.createElement('p');
      paragraph.textContent = message;
      cell.append(paragraph);
      if (clearable) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mt-quiet-button';
        button.dataset.clearFilters = '';
        button.textContent = 'Reset to current year';
        cell.append(button);
      }
    }
    if (stateRow) stateRow.hidden = false;
  },

  renderTermBand(terms) {
    const band = document.getElementById('mtTermBand');
    const overview = document.getElementById('mtOverviewYear');
    if (!band) return;

    if (overview) overview.textContent = this.scopeLabel();
    const phase = this.state.phase;
    const currentId = Number(phase?.currentPeriod?.academic_year_term_id || 0);
    const incomingId = Number(phase?.incoming?.id || 0);
    const recentId = Number(phase?.recent?.id || 0);

    const ordered = terms.toSorted((a, b) => {
      const left = this.termDates(a).start;
      const right = this.termDates(b).start;
      return left.localeCompare(right);
    });
    band.replaceChildren();
    if (!ordered.length) {
      const empty = document.createElement('li');
      empty.className = 'mt-band-empty';
      empty.textContent = 'No terms are recorded in this academic year.';
      band.append(empty);
      return;
    }

    ordered.forEach((term) => {
      const id = Number(term.id);
      const item = document.createElement('li');
      item.className = 'mt-band-item';
      let flag = '';
      if (id && id === currentId) {
        item.dataset.now = '';
        flag = 'In session';
      } else if (id && id === incomingId) {
        item.dataset.incoming = '';
        flag = 'Incoming';
      } else if (id && id === recentId) {
        item.dataset.recent = '';
        flag = 'Just ended';
      } else if (this.statusOf(term) === 'current') {
        item.dataset.current = '';
        flag = 'Current';
      }
      const title = document.createElement('strong');
      title.textContent = term.name || term.term_name || 'Term';
      const dates = this.termDates(term);
      const detail = document.createElement('span');
      detail.className = 'mt-band-dates';
      detail.textContent = `${this.fmtShortDate(dates.start)} – ${this.fmtShortDate(dates.end)}`;
      item.append(title, detail);
      if (flag) {
        const badge = document.createElement('span');
        badge.className = 'mt-band-flag';
        badge.textContent = flag;
        item.append(badge);
      }
      band.append(item);
    });
  },

  renderStats(terms) {
    const statuses = terms.map((term) => this.statusOf(term));
    const counts = {
      mtStatTotal: terms.length,
      mtStatActive: statuses.filter((status) => status === 'current').length,
      mtStatCompleted: statuses.filter((status) => status === 'completed').length,
      mtStatUpcoming: statuses.filter((status) => status === 'upcoming').length,
    };
    Object.entries(counts).forEach(([id, value]) => {
      const node = document.getElementById(id);
      if (node) node.textContent = String(value);
    });
  },

  renderResultCount(count) {
    const node = document.getElementById('mtResultCount');
    if (node) node.textContent = `${count} academic ${count === 1 ? 'term' : 'terms'} shown · ${this.scopeLabel()}`;
  },

  applySort() {
    const { column, direction } = this.state.sort;
    const table = document.getElementById('dataTable');
    const body = document.getElementById('mtTermsBody');
    const header = table?.tHead?.rows?.[0]?.cells?.[column];
    if (!body || !header) return;

    const rows = Array.from(body.rows).filter((row) => row.hasAttribute('data-term-row'));
    if (rows.length < 2) return;
    const type = header.dataset.type || 'text';
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    const factor = direction === 'descending' ? -1 : 1;
    const value = (row) => row.cells[column]?.dataset.value ?? row.cells[column]?.textContent.trim() ?? '';

    rows.sort((a, b) => {
      const left = value(a);
      const right = value(b);
      if (type === 'number') {
        const leftNumber = Number(left);
        const rightNumber = Number(right);
        if (left === '' && right !== '') return 1;
        if (right === '' && left !== '') return -1;
        return factor * ((Number.isNaN(leftNumber) ? 0 : leftNumber) - (Number.isNaN(rightNumber) ? 0 : rightNumber));
      }
      return factor * collator.compare(left, right);
    });

    rows.forEach((row) => {
      if (typeof body.moveBefore === 'function') body.moveBefore(row, null);
      else body.appendChild(row);
    });
  },

  syncSortIndicators() {
    const headers = document.getElementById('dataTable')?.tHead?.rows?.[0]?.cells;
    if (!headers) return;
    Array.from(headers).forEach((header) => header.removeAttribute('aria-sort'));
    headers[this.state.sort.column]?.setAttribute('aria-sort', this.state.sort.direction);
  },

  sortBy(button) {
    const th = button?.closest?.('th');
    if (!th) return;
    const column = th.cellIndex;
    const direction = this.state.sort.column === column && this.state.sort.direction === 'ascending'
      ? 'descending'
      : 'ascending';
    this.state.sort = { column, direction };
    this.syncSortIndicators();
    this.applySort();
    this.syncUrl();
    this.announce(`Sorted by ${button.textContent.trim()}, ${direction}.`);
  },

  syncUrl() {
    if (!window.history?.replaceState) return;
    try {
      const url = new URL(window.location.href);
      const yearId = this.yearFilterValue();
      const currentId = String(this.currentYear()?.id || '');
      const search = String(document.getElementById('searchInput')?.value || '').trim();
      const status = document.getElementById('filterSelect')?.value || '';
      const date = this.day(document.getElementById('dateFilter')?.value);
      if (yearId === 'all') url.searchParams.set('academic_year_id', 'all');
      else if (yearId && yearId !== currentId) url.searchParams.set('academic_year_id', yearId);
      else url.searchParams.delete('academic_year_id');
      if (search) url.searchParams.set('mtQ', search);
      else url.searchParams.delete('mtQ');
      if (status) url.searchParams.set('mtStatus', status);
      else url.searchParams.delete('mtStatus');
      if (date) url.searchParams.set('mtDate', date);
      else url.searchParams.delete('mtDate');
      window.history.replaceState(window.history.state, '', url);
    } catch (_) {
      // Filtering still works in embedded contexts without History access.
    }
  },

  readUrlState() {
    const params = new URLSearchParams(window.location.search);
    const search = document.getElementById('searchInput');
    const status = document.getElementById('filterSelect');
    const date = document.getElementById('dateFilter');
    const year = document.getElementById('yearFilter');
    if (search) search.value = params.get('mtQ') || '';
    if (status) status.value = params.get('mtStatus') || '';
    if (date) date.value = params.get('mtDate') || '';
    if (year) {
      const requested = params.get('academic_year_id') || '';
      const exists = requested && requested !== 'all'
        && this.state.years.some((item) => String(item.id) === requested);
      const selected = requested === 'all' ? 'all' : (exists ? requested : String(this.currentYear()?.id || ''));
      year.value = selected;
    }
    const clear = document.getElementById('mtClearFilters');
    if (clear) clear.hidden = !this.hasFilters();
  },

  applyYearMode() {
    if (!window.YearMode?.apply) return;
    const anchor = document.getElementById('mtYearBannerSlot') || document.getElementById('yearFilter');
    const yearId = Number(document.getElementById('yearFilter')?.value || 0) || Number(this.currentYear()?.id || 0);
    void Promise.resolve(window.YearMode.apply(yearId || undefined, anchor)).catch(() => {});
  },

  clearFilters() {
    const search = document.getElementById('searchInput');
    const status = document.getElementById('filterSelect');
    const date = document.getElementById('dateFilter');
    const year = document.getElementById('yearFilter');
    if (search) search.value = '';
    if (status) status.value = '';
    if (date) date.value = '';
    if (year) year.value = Number(this.currentYear()?.id || 0) ? String(this.currentYear().id) : 'all';
    this.renderTerms();
  },

  renderYearFilterOptions() {
    const select = document.getElementById('yearFilter');
    if (!select) return;
    const all = document.createElement('option');
    all.value = 'all';
    all.textContent = 'All academic years';
    select.replaceChildren(all);

    const years = this.state.years.toSorted((a, b) => String(b.start_date || '').localeCompare(String(a.start_date || '')));
    years.forEach((year) => {
      const option = document.createElement('option');
      option.value = String(year.id);
      const state = this.yearState(year);
      const suffix = state && state !== 'active' ? ` · ${state}` : '';
      option.textContent = `${this.yearName(year)}${suffix}`;
      select.append(option);
    });
    const current = this.currentYear();
    select.value = current ? String(current.id) : 'all';
  },

  nextYearCode() {
    const current = this.currentYear();
    if (!current || !this.state.years.length) return '';
    const ordered = this.state.years.toSorted((a, b) => String(a.start_date || '').localeCompare(String(b.start_date || '')));
    const index = ordered.findIndex((year) => String(year.id) === String(current.id));
    return String(ordered[index + 1]?.year_code || '');
  },

  async loadCalendarContext() {
    const toYear = this.nextYearCode();
    const requests = [
      this.API('GET', '/api/academic/current-period'),
      toYear
        ? this.API('GET', '/api/academic/year-transition/active', null, { to_year: toYear })
        : Promise.resolve(null),
    ];
    const [snapshotResult, transitionResult] = await Promise.allSettled(requests);
    this.state.period = this.settledValue(snapshotResult);
    this.state.transition = this.settledValue(transitionResult);
    this.renderPhase();
    this.renderTermBand(this.scopeTerms());
  },

  findTerm(id, terms = this.state.terms) {
    if (!id) return null;
    return terms.find((term) => String(term.id) === String(id)) || null;
  },

  recentTerm(period = null, terms = this.state.terms, today = this.todayISO()) {
    const current = period?.current_period || null;
    const direct = this.findTerm(current?.after_term_id || current?.academic_year_term_id, terms);
    if (direct) return direct;
    const closed = terms
      .filter((term) => this.termDates(term).end && this.termDates(term).end <= today)
      .sort((a, b) => this.termDates(b).end.localeCompare(this.termDates(a).end));
    return closed[0] || terms.toSorted((a, b) => this.termDates(b).end.localeCompare(this.termDates(a).end))[0] || null;
  },

  incomingTerm(period = null, terms = this.state.terms, today = this.todayISO()) {
    const direct = this.findTerm(period?.planning_term?.academic_year_term_id || period?.next_period?.academic_year_term_id, terms);
    if (direct) return direct;
    const upcoming = terms
      .filter((term) => this.termDates(term).start && this.termDates(term).start > today)
      .sort((a, b) => this.termDates(a).start.localeCompare(this.termDates(b).start));
    return upcoming[0] || null;
  },

  /**
   * Turn the school calendar into one plain-language state. Read-only: every
   * value comes from the current-period snapshot, the term records and the
   * active year-transition workflow; nothing here writes a status.
   */
  derivePhase({ period = null, transition = null, terms = [], yearTerms = [], today = this.todayISO() } = {}) {
    const current = period?.current_period || null;
    const workflow = transition?.data && typeof transition.data === 'object' ? transition.data : (transition || {});
    const switchDate = this.day(workflow.switch_date);
    const certified = Boolean(workflow.ready_for_new_year && switchDate);
    const inProgress = Boolean(transition && (transition.status === 'in_progress' || transition.current_stage));
    const allComplete = yearTerms.length > 0 && yearTerms.every((term) => this.statusOf(term) === 'completed');
    const recent = this.recentTerm(period, terms, today);
    const incoming = this.incomingTerm(period, terms, today);
    const common = {
      currentPeriod: current,
      recent,
      incoming,
      switchDate,
      linkLabel: 'Open Term Transition',
    };
    const yearCode = String(workflow.to_year_code || incoming?.year_code || '');

    if (allComplete && certified && switchDate > today) {
      return {
        ...common,
        key: 'waiting',
        eyebrow: 'Automatic cutover scheduled',
        title: 'Waiting for the system to switch',
        detail: `All terms in the current year are complete. ${yearCode ? `${yearCode} takes over` : 'The new year takes over'} on ${this.fmtDate(switchDate)}; no further action is needed.`,
        linkLabel: 'Review transition',
      };
    }
    if (allComplete && certified && switchDate && switchDate <= today) {
      return {
        ...common,
        key: 'due',
        eyebrow: 'Cutover date reached',
        title: 'Check the automatic switch',
        detail: `The certified switch date (${this.fmtDate(switchDate)}) has passed. Confirm the handover in Term Transition before editing the old year.`,
        linkLabel: 'Check cutover',
      };
    }
    if (allComplete && inProgress) {
      return {
        ...common,
        key: 'in-progress',
        eyebrow: 'Year transition in progress',
        title: 'Finish the transition checks',
        detail: 'All current-year terms are complete, but the automatic switch is not certified yet. Continue the checklist in Term Transition to schedule it.',
        linkLabel: 'Continue transition',
      };
    }
    if (allComplete) {
      return {
        ...common,
        key: 'schedule',
        eyebrow: 'End of academic year',
        title: 'Schedule the year transition',
        detail: 'Every term in the current year is complete and no certified switch is waiting. Open Term Transition to prepare the next year and set the cutover date.',
        linkLabel: 'Schedule transition',
      };
    }
    if (current?.kind === 'half_term') {
      return {
        ...common,
        key: 'half-term',
        eyebrow: 'Mid-term break',
        title: `${current.name} is in progress`,
        detail: `${current.label || current.name} runs from ${this.fmtDate(current.start_date)} to ${this.fmtDate(current.end_date)}. The term resumes after the break.`,
        linkLabel: '',
      };
    }
    if (current?.kind === 'holiday') {
      return {
        ...common,
        key: 'holiday',
        eyebrow: 'School holiday',
        title: `${current.name} is in progress`,
        detail: `${current.label || current.name} runs from ${this.fmtDate(current.start_date)} to ${this.fmtDate(current.end_date)}. The register keeps the recently completed term and the next term in view.`,
        linkLabel: '',
      };
    }
    if (current?.kind === 'term') {
      return {
        ...common,
        key: 'term',
        eyebrow: 'Term in session',
        title: `${current.name} is in session`,
        detail: `${current.label || current.name} runs until ${this.fmtDate(current.end_date)}.`,
        linkLabel: '',
      };
    }
    return {
      ...common,
      key: 'unknown',
      eyebrow: 'Calendar status',
      title: 'No active academic period',
      detail: 'Today is outside the configured academic calendar. Check the academic year dates before editing terms.',
      linkLabel: '',
    };
  },

  renderPhase() {
    const panel = document.getElementById('mtPhasePanel');
    if (!panel) return;
    const currentYear = this.currentYear();
    const yearTerms = currentYear
      ? this.state.terms.filter((term) => String(term.academic_year_id ?? term.year_id ?? term.year) === String(currentYear.id))
      : [];
    const phase = this.derivePhase({
      period: this.state.period,
      transition: this.state.transition,
      terms: this.state.terms,
      yearTerms,
    });
    this.state.phase = phase;
    panel.dataset.phase = phase.key;

    const text = (id, value) => {
      const node = document.getElementById(id);
      if (node) node.textContent = value;
    };
    text('mtPhaseEyebrow', phase.eyebrow);
    text('mtPhaseTitle', phase.title);
    text('mtPhaseDetail', phase.detail);
    text('mtPhaseAnnouncement', `${phase.title}. ${phase.detail}`);
    text('mtPhaseLinkLabel', phase.linkLabel || 'Open Term Transition');
    const link = document.getElementById('mtPhaseLink');
    if (link) link.hidden = !phase.linkLabel;
    this.renderPhaseCard('mtPhaseRecentCard', 'mtPhaseRecentTitle', 'mtPhaseRecentDates', phase.recent);
    this.renderPhaseCard('mtPhaseNextCard', 'mtPhaseNextTitle', 'mtPhaseNextDates', phase.incoming);
  },

  renderPhaseCard(cardId, titleId, datesId, term) {
    const card = document.getElementById(cardId);
    const title = document.getElementById(titleId);
    const dates = document.getElementById(datesId);
    if (!card || !title) return;
    card.hidden = !term;
    if (!term) return;
    title.textContent = `${term.name || term.term_name || 'Term'} · ${term.year_code || term.year_name || term.academic_year_name || '—'}`;
    if (!dates) return;
    const range = this.termDates(term);
    dates.replaceChildren();
    ['start', 'end'].forEach((key) => {
      const raw = range[key];
      if (!raw) return;
      const time = document.createElement('time');
      time.dateTime = raw;
      time.textContent = this.fmtShortDate(raw);
      dates.append(time);
    });
    if (dates.childNodes.length === 2) {
      const separator = document.createTextNode(' – ');
      dates.insertBefore(separator, dates.childNodes[1]);
    }
  },

  renderFormOptions() {
    const target = document.getElementById('recordYear');
    if (!target) return;
    const years = this.state.years.toSorted((a, b) => String(b.start_date || '').localeCompare(String(a.start_date || '')));
    target.replaceChildren();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select academic year';
    target.append(placeholder);
    years.forEach((year) => {
      const option = document.createElement('option');
      option.value = String(year.id);
      option.textContent = this.yearName(year);
      target.append(option);
    });
    const current = this.currentYear();
    if (current) target.value = String(current.id);
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

  announce(message) {
    const status = document.getElementById('mtTableStatus');
    if (status) status.textContent = message;
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;

    document.getElementById('mtAddBtn')?.addEventListener('click', () => this.showAddModal());
    document.getElementById('mtExportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('mtPrintBtn')?.addEventListener('click', () => this.print());
    document.getElementById('mtClearFilters')?.addEventListener('click', () => this.clearFilters());

    ['yearFilter', 'filterSelect', 'dateFilter'].forEach((id) => {
      document.getElementById(id)?.addEventListener('change', () => {
        const clear = document.getElementById('mtClearFilters');
        if (clear) clear.hidden = !this.hasFilters();
        this.renderTerms();
      });
    });
    document.getElementById('searchInput')?.addEventListener('input', () => {
      const clear = document.getElementById('mtClearFilters');
      if (clear) clear.hidden = !this.hasFilters();
      this.renderTerms();
    });

    const table = document.getElementById('dataTable');
    if (table) {
      table.addEventListener('command', (event) => {
        if (event.command === '--sort') this.sortBy(event.source);
      });
      if (!('commandForElement' in HTMLButtonElement.prototype)) {
        table.querySelectorAll('thead button[command="--sort"]').forEach((button) => {
          button.addEventListener('click', () => this.sortBy(button));
        });
      }
    }

    document.getElementById('mtTermsBody')?.addEventListener('click', (event) => {
      const edit = event.target.closest?.('[data-action="edit"]');
      if (edit) this.editTerm(edit.dataset.termId);
      if (event.target.closest?.('[data-clear-filters]')) this.clearFilters();
    });

    const dialog = document.getElementById('mtFormDialog');
    const form = document.getElementById('recordForm');
    dialog?.addEventListener('close', () => {
      dialog.returnValue = '';
      this.clearFormError();
    });
    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      void this.saveRecord(event);
    });
    form?.addEventListener('input', (event) => {
      if (event.target.getAttribute?.('aria-invalid') === 'true') this.clearFormError();
    });
    document.getElementById('mtFormCancel')?.addEventListener('click', () => dialog?.close());
    document.getElementById('mtFormClose')?.addEventListener('click', () => dialog?.close());
  },

  applyPermissions() {
    const auth = window.AuthContext;
    const csv = document.getElementById('mtExportBtn');
    if (csv && auth?.canExport && !auth.canExport('academic')) {
      csv.disabled = true;
      csv.title = 'Your account cannot export academic data.';
    }
    const print = document.getElementById('mtPrintBtn');
    if (print && auth?.canPrint && !auth.canPrint('academic')) {
      print.disabled = true;
      print.title = 'Your account cannot print academic data.';
    }
  },

  showAddModal() {
    const form = document.getElementById('recordForm');
    const dialog = document.getElementById('mtFormDialog');
    if (!form || !dialog) return;
    form.reset();
    this.clearFormError();
    document.getElementById('recordId').value = '';
    const title = document.getElementById('mtFormTitle');
    if (title) title.textContent = 'Add term';
    const selectedYear = Number(document.getElementById('yearFilter')?.value || 0) || Number(this.currentYear()?.id || 0);
    if (selectedYear) document.getElementById('recordYear').value = String(selectedYear);
    dialog.showModal();
    document.getElementById('recordName')?.focus();
  },

  editTerm(id) {
    const term = this.state.terms.find((item) => Number(item.id) === Number(id));
    const dialog = document.getElementById('mtFormDialog');
    if (!term || !dialog) return;
    const form = document.getElementById('recordForm');
    form.reset();
    this.clearFormError();
    document.getElementById('recordId').value = term.id;
    document.getElementById('recordName').value = term.name || term.term_name || '';
    document.getElementById('recordYear').value = term.academic_year_id ?? term.year_id ?? term.year ?? '';
    const dates = this.termDates(term);
    document.getElementById('recordStartDate').value = dates.start;
    document.getElementById('recordEndDate').value = dates.end;
    document.getElementById('recordHalfTermStart').value = this.day(term.half_term_start);
    document.getElementById('recordHalfTermEnd').value = this.day(term.half_term_end);
    document.getElementById('recordStatus').value = this.statusOf(term) || 'upcoming';
    const title = document.getElementById('mtFormTitle');
    if (title) title.textContent = 'Edit term';
    dialog.showModal();
    document.getElementById('recordName')?.focus();
  },

  setInvalid(field, invalid) {
    if (!field) return;
    if (invalid) {
      field.setAttribute('aria-invalid', 'true');
      const describedBy = String(field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
      if (!describedBy.includes('mtFormError')) describedBy.push('mtFormError');
      field.setAttribute('aria-describedby', describedBy.join(' '));
      return;
    }
    field.removeAttribute('aria-invalid');
    const describedBy = String(field.getAttribute('aria-describedby') || '').split(/\s+/)
      .filter((token) => token && token !== 'mtFormError');
    if (describedBy.length) field.setAttribute('aria-describedby', describedBy.join(' '));
    else field.removeAttribute('aria-describedby');
  },

  clearFormError() {
    const dialog = document.getElementById('mtFormDialog');
    dialog?.querySelectorAll('[aria-invalid="true"]').forEach((field) => this.setInvalid(field, false));
    const error = document.getElementById('mtFormError');
    if (error) {
      error.textContent = '';
      error.hidden = true;
    }
  },

  setFormError(message, fields = []) {
    const error = document.getElementById('mtFormError');
    if (error) {
      error.textContent = message;
      error.hidden = false;
    }
    fields.forEach((field) => this.setInvalid(field, true));
    fields[0]?.focus?.();
  },

  async saveRecord(event) {
    this.clearFormError();
    const id = document.getElementById('recordId')?.value;
    const nameField = document.getElementById('recordName');
    const yearField = document.getElementById('recordYear');
    const startField = document.getElementById('recordStartDate');
    const endField = document.getElementById('recordEndDate');
    const halfStartField = document.getElementById('recordHalfTermStart');
    const halfEndField = document.getElementById('recordHalfTermEnd');
    const name = String(nameField?.value || '').trim();
    const academicYearId = Number(yearField?.value || 0);
    const start = this.day(startField?.value);
    const end = this.day(endField?.value);
    const halfStart = this.day(halfStartField?.value);
    const halfEnd = this.day(halfEndField?.value);

    const invalid = [];
    const reasons = [];
    if (!name) {
      invalid.push(nameField);
      reasons.push('Enter a term name.');
    }
    if (!academicYearId) {
      invalid.push(yearField);
      reasons.push('Choose an academic year.');
    }
    if (!start) {
      invalid.push(startField);
      reasons.push('Choose an opening date.');
    }
    if (!end) {
      invalid.push(endField);
      reasons.push('Choose a closing date.');
    } else if (start && end < start) {
      invalid.push(endField);
      reasons.push('The closing date must be after the opening date.');
    }
    if (Boolean(halfStart) !== Boolean(halfEnd)) {
      invalid.push(halfStart ? halfEndField : halfStartField);
      reasons.push('Enter both half-term dates, or leave both blank.');
    } else if (halfStart && halfEnd) {
      if (halfStart < start || halfEnd > end || halfEnd < halfStart) {
        invalid.push(halfStartField, halfEndField);
        reasons.push('Half-term dates must sit inside the term, in order.');
      }
    }
    if (invalid.length) return this.setFormError(reasons.join(' '), invalid);

    const submit = event?.currentTarget?.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    const payload = {
      name,
      academic_year_id: academicYearId,
      start_date: start,
      end_date: end,
      half_term_start: halfStart || null,
      half_term_end: halfEnd || null,
      status: document.getElementById('recordStatus')?.value || 'upcoming',
    };
    const termNumber = Number(name.match(/term\s*(\d+)/i)?.[1] || 1);
    try {
      if (id) {
        await this.API('PUT', `/api/academic/terms/${Number(id)}`, payload);
      } else {
        await this.API('POST', '/api/academic/terms', { ...payload, term_number: termNumber });
      }
      document.getElementById('mtFormDialog')?.close();
      this.notify(id ? 'Term updated.' : 'Term created.', 'success');
      await this.load();
    } catch (error) {
      this.setFormError(error.message || 'Could not save the term.');
      this.notify(error.message || 'Could not save term.', 'danger');
    } finally {
      if (submit) submit.disabled = false;
    }
  },

  refresh() {
    return this.load();
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) {
      return this.notify('You do not have permission to export academic data.', 'warning');
    }
    const rows = this.filteredTerms();
    if (!rows.length) return this.notify('There are no terms to export.', 'warning');
    if (!window.KingswayFileLifecycle?.exportText) {
      return this.notify('Export is not available in this browser session.', 'warning');
    }
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [
      ['Term', 'Academic Year', 'Opens', 'Closes', 'Half-term opens', 'Half-term closes', 'Weeks', 'Status'],
      ...rows.map((term) => {
        const dates = this.termDates(term);
        return [
          term.name || term.term_name || '',
          term.year_name || term.academic_year_name || term.year_code || term.year || '',
          dates.start,
          dates.end,
          this.day(term.half_term_start),
          this.day(term.half_term_end),
          term.weeks ?? term.week_count ?? '',
          this.statusOf(term),
        ];
      }),
    ].map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle.exportText(csv, 'academic_terms.csv', 'text/csv;charset=utf-8;');
  },

  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
      return this.notify('You do not have permission to print academic data.', 'warning');
    }
    const node = document.getElementById('mtPrintContext');
    const count = this.filteredTerms().length;
    if (node) {
      const today = this.fmtDate(this.todayISO());
      node.textContent = `Term Register · ${count} academic ${count === 1 ? 'term' : 'terms'} shown · ${this.scopeLabel()} · ${today}`;
    }
    window.print();
  },

  notify(message, type = 'info') {
    if (window.showNotification) window.showNotification(message, type);
    else if (window.showToast) window.showToast(message, type);
    else alert(message);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.applyPermissions();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.('manage_terms', {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
        await this.loadCalendarContext();
      },
    }, ['academic']);
  },
};

window.manageTermsController = manageTermsController;
window.ManageTermsController = manageTermsController;
document.addEventListener('DOMContentLoaded', () => manageTermsController.init().catch((error) => {
  console.error('Manage terms page initialization failed:', error);
  window.ManageTermsController?.showTermsState?.('Page setup failed. Refresh to try again.', 'mt-empty--error');
}));
