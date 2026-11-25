/**
 * Academic Years Controller
 * Manages academic year definitions and the school-ledger register view.
 *
 * New years and their term structure are still created only in Year Transition.
 * This page edits year metadata, inspects term readiness, and applies bounded
 * bulk date shifts through the existing year-update endpoint.
 */
var academicYearsController = {
  state: {
    referenceCache: null,
    years: [],
    termCountsAvailable: false,
    view: 'all',
    search: '',
    sort: { column: 1, direction: 'ascending' },
    selected: new Set(),
    accessCache: new Map(),
    termCache: new Map(),
    detailYearId: null,
    editingYearId: null,
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

  addDays(value, days) {
    const raw = this.day(value);
    const date = new Date(`${raw}T00:00:00Z`);
    if (!raw || Number.isNaN(date.getTime())) return '';
    date.setUTCDate(date.getUTCDate() + Number(days || 0));
    return date.toISOString().slice(0, 10);
  },

  listFrom(response) {
    let value = response;
    for (let depth = 0; depth < 6; depth += 1) {
      if (Array.isArray(value)) return value;
      if (!value || typeof value !== 'object') return null;
      value = value.years ?? value.academic_years ?? value.terms ?? value.academic_terms
        ?? value.data ?? value.items ?? value.rows ?? value.records;
    }
    return Array.isArray(value) ? value : null;
  },

  yearLabel(year) {
    return String(year?.year_name || year?.name || year?.year_code || '').trim().toLowerCase();
  },

  yearName(year) {
    return String(year?.year_name || year?.name || year?.year_code || '—');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read('academic', 'manager-overview');
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn('Academic Years Node view failed; using direct reads:', error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const years = cache?.years !== undefined ? cache.years : await this.API('GET', '/api/academic/years/list');
      this.state.years = this.listFrom(years) || [];
      await Promise.all([this.loadTermCounts(), this.loadYearAccess()]);
      this.render();
      await this.loadCurrentContext();
    } catch (error) {
      console.error('Failed to load academic years:', error);
      const body = document.getElementById('ayYearsBody');
      if (body) {
        body.innerHTML = '<tr><td colspan="7"><div class="ay-empty"><h3>The register could not be loaded</h3><p>Check your connection, then refresh this page.</p></div></td></tr>';
      }
      const yearBox = document.getElementById('currentYearInfo');
      const termBox = document.getElementById('currentTermInfo');
      if (yearBox) yearBox.innerHTML = '<p class="ay-overview-error">Could not load academic year details.</p>';
      if (termBox) termBox.innerHTML = '<p class="ay-overview-error">Could not load current term details.</p>';
    }
  },

  getCache(key) {
    return this.state.referenceCache?.[key];
  },

  async loadTermCounts() {
    this.state.termCountsAvailable = false;
    this.state.termCache.clear();
    try {
      const response = this.state.referenceCache?.terms !== undefined
        ? this.state.referenceCache.terms
        : await window.API.academic.listTerms();
      const terms = this.listFrom(response);
      if (!terms) throw new Error('Academic terms response was not a list.');
      const counts = new Map();
      const countsByLabel = new Map();
      terms.forEach((term) => {
        const rawYearId = term.academic_year_id ?? term.year_id ?? term.year;
        const yearId = Number(rawYearId);
        if (Number.isInteger(yearId) && yearId > 0) {
          counts.set(yearId, (counts.get(yearId) || 0) + 1);
          if (!this.state.termCache.has(yearId)) this.state.termCache.set(yearId, []);
          this.state.termCache.get(yearId).push(term);
        }
        const label = String(term.year_name || term.academic_year_name || (Number.isNaN(yearId) ? rawYearId : '') || '').trim().toLowerCase();
        if (label) countsByLabel.set(label, (countsByLabel.get(label) || 0) + 1);
      });
      this.state.years = this.state.years.map((year) => ({
        ...year,
        // Count from the same complete term collection used by the term
        // manager. A stale/partial Node composition may still carry an old
        // zero in `term_count`; that must not override the relationship
        // verified by the term rows.
        term_count: counts.get(Number(year.id))
          ?? countsByLabel.get(this.yearLabel(year))
          ?? (Number.isFinite(Number(year.term_count)) ? Number(year.term_count) : null),
      }));
      this.state.termCountsAvailable = true;
    } catch (error) {
      console.error('Failed to load academic year term counts:', error);
    }
  },

  async loadYearAccess() {
    this.state.accessCache.clear();
    await Promise.all(this.state.years.map(async (year) => {
      const id = Number(year.id);
      if (!id) return;
      let access = null;
      try {
        const response = await window.API.academic.getYearAccess(id);
        access = response?.data || response || null;
      } catch (error) {
        access = null;
      }
      this.state.accessCache.set(id, {
        ...(access || {}),
        editable: access ? access.editable !== false : this.yearState(year) !== 'completed',
      });
    }));
  },

  isEditable(year) {
    const id = Number(year.id);
    const access = this.state.accessCache.get(id);
    if (access && access.editable === false) return false;
    return access?.editable === true || this.yearState(year) !== 'completed';
  },

  async termsForYear(id) {
    const key = Number(id);
    if (this.state.termCache.has(key)) return this.state.termCache.get(key);
    try {
      const response = await window.API.academic.listTerms({ academic_year_id: key });
      const terms = this.listFrom(response) || [];
      this.state.termCache.set(key, terms);
      return terms;
    } catch (error) {
      console.error(`Failed to load terms for academic year ${key}:`, error);
      return [];
    }
  },

  async loadCurrentContext() {
    const yearBox = document.getElementById('currentYearInfo');
    const termBox = document.getElementById('currentTermInfo');
    // The incoming year is also "active" during a transition, so only the
    // year's state (or the is_current flag) identifies the current one.
    const currentYear = this.state.years.find((year) => this.yearState(year) === 'current') || null;
    if (!currentYear) {
      if (yearBox) yearBox.innerHTML = '<p class="ay-overview-message">No current academic year is configured.</p>';
      if (termBox) termBox.innerHTML = '<p class="ay-overview-message">No current term is configured. Start Year Transition when the next year is ready.</p>';
      return;
    }

    const label = this.esc(this.yearName(currentYear));
    const termCount = Number(currentYear.term_count);
    const termNote = Number.isFinite(termCount) && termCount > 0
      ? `<p class="ay-year-note">${termCount} term${termCount === 1 ? '' : 's'} recorded in this year.</p>`
      : '';
    if (yearBox) {
      yearBox.innerHTML = `<p class="ay-year-name">${label}</p>
        <div class="ay-date-range">${this.fmtDate(currentYear.start_date)}<span aria-hidden="true">—</span>${this.fmtDate(currentYear.end_date)}</div>
        ${termNote}`;
    }

    try {
      const terms = await this.termsForYear(currentYear.id);
      const term = terms.find((item) =>
        item.is_current === true || Number(item.is_current) === 1 ||
        String(item.status || '').trim().toLowerCase() === 'current'
      ) || null;
      if (!termBox) return;
      if (term) {
        const name = this.esc(term.name || term.term_name || 'Current term');
        termBox.innerHTML = this.termMarkup('In session', [
          { tone: 'current', title: name, detail: `${this.fmtDate(term.start_date)} – ${this.fmtDate(term.end_date)}` },
        ]);
      } else {
        termBox.innerHTML = await this.betweenTermsHtml(terms);
      }
    } catch (error) {
      console.error('Failed to load current term:', error);
      if (termBox) termBox.innerHTML = '<p class="ay-overview-error">Could not load current term details.</p>';
    }
  },

  termMarkup(state, items) {
    return `<p class="ay-term-state">${this.esc(state)}</p>
      <ol class="ay-timeline">${items.map((item) => `<li class="ay-timeline-item ay-timeline-item--${this.esc(item.tone)}">
        <strong>${this.esc(item.title)}</strong><span>${item.detail}</span>
      </li>`).join('')}</ol>`;
  },

  // Between terms (holiday, or after the last term of the year): say which
  // term closed last and when the next one opens.
  async betweenTermsHtml(terms) {
    const today = this.todayLocal();
    const day = (v) => this.day(v);
    const closed = terms.filter((t) => day(t.end_date) && day(t.end_date) < today)
      .sort((a, b) => day(b.end_date).localeCompare(day(a.end_date)))[0] || null;
    let next = terms.filter((t) => day(t.start_date) > today)
      .sort((a, b) => day(a.start_date).localeCompare(day(b.start_date)))[0] || null;
    let nextYear = null;
    if (!next) {
      nextYear = this.state.years.find((year) => this.yearState(year) === 'upcoming') || null;
      if (nextYear) {
        const list = await this.termsForYear(nextYear.id);
        next = list.toSorted((a, b) => day(a.start_date).localeCompare(day(b.start_date)))[0] || null;
      }
    }

    const name = (t) => this.esc(t.name || t.term_name || 'Term');
    const items = [];
    if (closed) {
      items.push({ tone: 'closed', title: `${name(closed)} closed`, detail: this.fmtDate(closed.end_date) });
    }
    if (next) {
      items.push({
        tone: 'next',
        title: `${name(next)}${nextYear ? ` of ${this.esc(this.yearName(nextYear))}` : ''} opens`,
        detail: this.fmtDate(next.start_date),
      });
    }
    return items.length
      ? this.termMarkup('Between terms', items)
      : '<p class="ay-overview-message">No term is running.</p>';
  },

  matchesView(year, view) {
    if (view === 'all') return true;
    const state = this.yearState(year);
    const active = state === 'current' || state === 'upcoming';
    const past = state === 'completed' || state === 'archived'
      || (year.end_date && this.day(year.end_date) < this.todayLocal());
    return view === 'active' ? active : past;
  },

  matchesSearch(year) {
    if (!this.state.search) return true;
    const haystack = [
      this.yearName(year),
      this.yearStatus(year).label,
      year.start_date || '',
      year.end_date || '',
    ].join(' ').toLowerCase();
    return haystack.includes(this.state.search);
  },

  visibleYears() {
    return this.state.years.filter((year) => this.matchesView(year, this.state.view) && this.matchesSearch(year));
  },

  selectedYears() {
    return this.state.years.filter((year) => this.state.selected.has(String(year.id)));
  },

  render() {
    const years = this.visibleYears();
    const body = document.getElementById('ayYearsBody');
    if (!body) return;

    const allowed = new Set(years.map((year) => String(year.id)));
    this.state.selected = new Set([...this.state.selected].filter((id) => allowed.has(id)));

    if (!years.length) {
      const filtered = Boolean(this.state.search) || this.state.view !== 'all';
      body.innerHTML = `<tr><td colspan="7"><div class="ay-empty">
        <i class="bi bi-calendar-x ay-empty-icon" aria-hidden="true"></i>
        <h3>${filtered ? 'No matching years' : 'No academic years recorded'}</h3>
        <p>${filtered ? 'Try another search term or view the full register.' : 'Years appear here after Year Transition creates them.'}</p>
        ${filtered ? '<button class="ay-quiet-button" type="button" data-clear-filters>Show all years</button>' : ''}
      </div></td></tr>`;
    } else {
      body.innerHTML = years.map((year) => this.rowHtml(year)).join('');
      this.applySort();
    }

    this.syncSortIndicators();
    this.syncSelectionControls();
    this.updateCounts();
    this.updatePrintContext(years.length);
    this.syncUrl();
    this.announce(`${years.length} academic ${years.length === 1 ? 'year' : 'years'} shown.`);
  },

  rowHtml(year) {
    const id = Number(year.id) || '';
    const label = this.esc(this.yearName(year));
    const status = this.yearStatus(year);
    const state = this.yearState(year);
    const tone = ['current', 'upcoming'].includes(state) ? state : 'neutral';
    const editable = this.isEditable(year);
    const termCount = this.state.termCountsAvailable && year.term_count != null && Number.isFinite(Number(year.term_count))
      ? Number(year.term_count)
      : null;
    const terms = termCount === null
      ? '<span class="ay-muted-cell" title="Term counts could not be matched to this academic year">—</span>'
      : String(termCount);
    const checked = this.state.selected.has(String(id)) ? ' checked' : '';
    const disabled = editable ? '' : ' disabled title="This academic year is read-only for your account."';

    return `<tr data-year-id="${this.esc(id)}">
      <td class="ay-select-col" data-label="">
        <input class="form-check-input" type="checkbox" data-select-year="${this.esc(id)}" aria-label="Select ${label}"${checked}${disabled}>
      </td>
      <th scope="row" data-value="${label}">${label}</th>
      <td data-label="Opens" data-value="${this.esc(this.day(year.start_date))}">${this.fmtDate(year.start_date)}</td>
      <td data-label="Closes" data-value="${this.esc(this.day(year.end_date))}">${this.fmtDate(year.end_date)}</td>
      <td data-label="Terms" data-value="${termCount === null ? '' : termCount}">${terms}</td>
      <td data-label="Status" data-value="${this.esc(status.label.toLowerCase())}"><span class="ay-status ay-status--${tone}">${this.esc(status.label)}</span></td>
      <td data-label="Record">
        <div class="ay-row-actions">
          <button type="button" class="ay-detail-button" data-detail-year="${this.esc(id)}" aria-label="View details for ${label}"><i class="bi bi-list-check" aria-hidden="true"></i><span>Details</span></button>
          <button type="button" class="ay-edit-button" data-edit-year="${this.esc(id)}" data-year-write aria-label="Edit ${label}"${disabled}><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>
        </div>
      </td>
    </tr>`;
  },

  applySort() {
    const { column, direction } = this.state.sort;
    const table = document.getElementById('academicYearsTable');
    const tbody = document.getElementById('ayYearsBody');
    const header = table?.tHead?.rows?.[0]?.cells?.[column];
    if (!tbody || !header || column == null) return;

    const rows = Array.from(tbody.rows);
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
      if (typeof tbody.moveBefore === 'function') tbody.moveBefore(row, null);
      else tbody.appendChild(row);
    });
  },

  syncSortIndicators() {
    const headers = document.getElementById('academicYearsTable')?.tHead?.rows?.[0]?.cells;
    if (!headers) return;
    Array.from(headers).forEach((header) => header.removeAttribute('aria-sort'));
    headers[this.state.sort.column]?.setAttribute('aria-sort', this.state.sort.direction);
  },

  sortBy(button) {
    const table = document.getElementById('academicYearsTable');
    const th = button?.closest?.('th');
    if (!table || !th) return;
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

  readUrlState() {
    const params = new URLSearchParams(window.location.search);
    const view = params.get('ayView');
    if (['all', 'active', 'past'].includes(view)) this.state.view = view;
    this.state.search = String(params.get('ayQ') || '').trim().toLowerCase();
    const column = Number(params.get('aySort'));
    const direction = params.get('ayDir') === 'descending' ? 'descending' : 'ascending';
    if (Number.isInteger(column) && column >= 1 && column <= 6) {
      this.state.sort = { column, direction };
    }
    const search = document.getElementById('aySearch');
    if (search) search.value = this.state.search;
  },

  syncUrl() {
    if (!window.history?.replaceState) return;
    const url = new URL(window.location.href);
    if (this.state.view === 'all') url.searchParams.delete('ayView');
    else url.searchParams.set('ayView', this.state.view);
    if (this.state.search) url.searchParams.set('ayQ', this.state.search);
    else url.searchParams.delete('ayQ');
    if (this.state.sort.column === 1 && this.state.sort.direction === 'ascending') {
      url.searchParams.delete('aySort');
      url.searchParams.delete('ayDir');
    } else {
      url.searchParams.set('aySort', String(this.state.sort.column));
      url.searchParams.set('ayDir', this.state.sort.direction);
    }
    window.history.replaceState(window.history.state, '', url);
  },

  syncSelectionControls() {
    const editableVisible = this.visibleYears().filter((year) => this.isEditable(year));
    const selectedEditable = this.selectedYears().filter((year) => this.isEditable(year));
    const all = document.getElementById('aySelectAll');
    if (all) {
      const chosen = editableVisible.filter((year) => this.state.selected.has(String(year.id))).length;
      all.disabled = editableVisible.length === 0;
      all.checked = editableVisible.length > 0 && chosen === editableVisible.length;
      all.indeterminate = chosen > 0 && chosen < editableVisible.length;
      all.setAttribute('aria-label', `Select all ${editableVisible.length} editable academic years`);
    }
    const bulkButton = document.getElementById('ayBulkBtn');
    if (bulkButton) bulkButton.disabled = selectedEditable.length === 0;
    const count = document.getElementById('ayBulkCount');
    if (count) count.textContent = selectedEditable.length ? ` (${selectedEditable.length})` : '';
  },

  updateCounts() {
    const counts = {
      all: this.state.years.length,
      active: this.state.years.filter((year) => this.matchesView(year, 'active')).length,
      past: this.state.years.filter((year) => this.matchesView(year, 'past')).length,
    };
    const page = document.getElementById('academicYearsPage');
    page?.querySelectorAll('[data-view]').forEach((button) => {
      const view = button.dataset.view || 'all';
      const count = counts[view] ?? 0;
      const badge = button.querySelector('[data-count]');
      if (badge) badge.textContent = String(count);
      button.setAttribute('aria-label', `${view} academic years, ${count}`);
      button.classList.toggle('active', view === this.state.view);
      button.setAttribute('aria-pressed', String(view === this.state.view));
    });
  },

  updatePrintContext(count) {
    const node = document.getElementById('ayPrintContext');
    if (!node) return;
    const today = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date());
    node.textContent = `Academic Years Register · ${count} academic ${count === 1 ? 'year' : 'years'} shown · ${today}`;
  },

  announce(message) {
    const status = document.getElementById('ayTableStatus');
    if (status) status.textContent = message;
  },

  yearState(year) {
    const state = String(year?.state || '').trim().toLowerCase();
    if (state) return state;
    if (year?.is_current === true || Number(year?.is_current) === 1) return 'current';
    const status = String(year?.status || year?.year_status || '').trim().toLowerCase();
    return status === 'completed' ? 'completed' : '';
  },

  yearStatus(year) {
    const labels = {
      current: { label: 'Current', className: 'success' },
      upcoming: { label: 'Upcoming', className: 'info' },
      completed: { label: 'Completed', className: 'secondary' },
      archived: { label: 'Archived', className: 'secondary' },
    };
    const state = this.yearState(year);
    if (labels[state]) return labels[state];
    const status = String(year.status || year.year_status || '').trim().toLowerCase();
    return { label: status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Inactive', className: 'secondary' };
  },

  todayLocal() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  },

  fmtDate(value) {
    const raw = this.day(value);
    if (!raw) return '<span class="ay-muted-cell">—</span>';
    const date = new Date(`${raw}T00:00:00`);
    if (Number.isNaN(date.getTime())) return this.esc(value);
    const text = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
    return `<time datetime="${this.esc(raw)}">${this.esc(text)}</time>`;
  },

  // Readiness is a register check derived from the same term rows shown to
  // staff. It does not change records and is not an official certification.
  yearReadiness(year, terms) {
    const checks = [];
    const issues = [];
    const notes = [];
    const start = this.day(year.start_date);
    const end = this.day(year.end_date);

    if (start && end) checks.push('Opening and closing dates are present.');
    else issues.push('The year is missing opening or closing dates.');

    if (terms.length === 3) checks.push('Three terms are recorded.');
    else issues.push(`${terms.length} of 3 terms are recorded.`);

    const dated = terms.filter((term) => this.day(term.start_date) && this.day(term.end_date));
    if (dated.length !== terms.length) issues.push('Some terms are missing dates.');

    const sorted = dated.toSorted((a, b) => this.day(a.start_date).localeCompare(this.day(b.start_date)));
    if (start && end && sorted.length) {
      const outside = sorted.filter((term) => this.day(term.start_date) < start || this.day(term.end_date) > end);
      if (outside.length) issues.push(`${outside.length} term${outside.length === 1 ? '' : 's'} fall outside the year dates.`);
      else checks.push('Term dates sit inside the year.');
    }

    let hasOverlap = false;
    for (let index = 1; index < sorted.length; index += 1) {
      const previous = sorted[index - 1];
      const current = sorted[index];
      const expected = this.addDays(this.day(previous.end_date), 1);
      const currentStart = this.day(current.start_date);
      const previousName = previous.name || previous.term_name || 'Earlier term';
      const currentName = current.name || current.term_name || 'next term';
      if (expected > currentStart) {
        issues.push(`${this.esc(previousName)} overlaps ${this.esc(currentName)}.`);
        hasOverlap = true;
      } else if (expected < currentStart) {
        // School years include the April and August holidays, so a break is
        // expected. Report it for context but never treat it as an error.
        const breakDays = Math.round((Date.parse(`${currentStart}T00:00:00Z`) - Date.parse(`${expected}T00:00:00Z`)) / 86400000);
        notes.push(`${breakDays}-day break after ${previousName} before ${currentName}.`);
      }
    }
    if (sorted.length > 1 && !hasOverlap) checks.push('No overlapping term dates.');

    return {
      level: issues.length ? 'attention' : 'ready',
      summary: issues.length ? 'Needs attention' : 'Ready',
      checks,
      issues,
      notes,
      terms: sorted,
    };
  },

  async openDetail(id) {
    const year = this.state.years.find((item) => Number(item.id) === Number(id));
    const body = document.getElementById('ayDetailBody');
    const title = document.getElementById('ayDetailTitle');
    const drawerElement = document.getElementById('ayDetailDrawer');
    if (!year || !body || !drawerElement) return;
    this.state.detailYearId = Number(year.id);
    if (title) title.textContent = this.yearName(year);
    body.innerHTML = '<p class="ay-overview-message"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading term details…</p>';
    bootstrap.Offcanvas.getOrCreateInstance(drawerElement).show();

    const terms = await this.termsForYear(year.id);
    const readiness = this.yearReadiness(year, terms);
    body.innerHTML = this.detailHtml(year, terms, readiness);
  },

  detailHtml(year, terms, readiness) {
    const id = Number(year.id);
    const status = this.yearStatus(year);
    const detailLink = (route, label, icon) => `<a class="ay-drawer-link" href="home.php?route=${this.esc(route)}&amp;academic_year_id=${this.esc(id)}">
      <i class="bi ${icon}" aria-hidden="true"></i><span>${this.esc(label)}</span>
    </a>`;

    const checks = readiness.checks.length
      ? `<ul class="ay-check-list">${readiness.checks.map((text) => `<li class="ay-check"><i class="bi bi-check-circle" aria-hidden="true"></i><span>${this.esc(text)}</span></li>`).join('')}</ul>`
      : '';
    const issues = readiness.issues.length
      ? `<ul class="ay-check-list">${readiness.issues.map((text) => `<li class="ay-check ay-check--issue"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>${text}</span></li>`).join('')}</ul>`
      : '<p class="ay-detail-clear"><i class="bi bi-check2-circle" aria-hidden="true"></i> No register issues found.</p>';
    const notes = readiness.notes.length
      ? `<ul class="ay-check-list ay-note-list">${readiness.notes.map((text) => `<li class="ay-check ay-check--note"><i class="bi bi-moon-stars" aria-hidden="true"></i><span>${this.esc(text)}</span></li>`).join('')}</ul>`
      : '';

    const termItems = terms.length
      ? `<ol class="ay-term-list">${terms.map((term) => {
        const termStatus = String(term.status || '').trim().toLowerCase();
        return `<li class="ay-term-card">
          <div class="ay-term-card-head">
            <strong>${this.esc(term.name || term.term_name || 'Term')}</strong>
            ${termStatus ? `<span class="ay-status">${this.esc(termStatus.charAt(0).toUpperCase() + termStatus.slice(1))}</span>` : ''}
          </div>
          <div class="ay-term-card-dates">${this.fmtDate(term.start_date)} <span aria-hidden="true">→</span> ${this.fmtDate(term.end_date)}</div>
          ${this.day(term.half_term_start) || this.day(term.half_term_end)
            ? `<div class="ay-term-half">Half term: ${this.fmtDate(term.half_term_start)} – ${this.fmtDate(term.half_term_end)}</div>`
            : ''}
        </li>`;
      }).join('')}</ol>`
      : '<p class="ay-overview-message">No terms are recorded for this year. New years are created in Year Transition.</p>';

    return `<div class="ay-detail-summary ay-detail-summary--${readiness.level}">
      <span class="ay-readiness ay-readiness--${readiness.level}">${this.esc(readiness.summary)}</span>
      <span>${this.esc(status.label)} · ${terms.length} recorded term${terms.length === 1 ? '' : 's'}</span>
    </div>
    <dl class="ay-detail-facts">
      <div><dt>Opens</dt><dd>${this.fmtDate(year.start_date)}</dd></div>
      <div><dt>Closes</dt><dd>${this.fmtDate(year.end_date)}</dd></div>
    </dl>
    <section class="ay-detail-section" aria-labelledby="ayDetailCheckTitle">
      <h3 id="ayDetailCheckTitle">Register check</h3>
      ${checks}${issues}${notes}
      <p class="ay-detail-note">Review only. This check does not certify the year or change school records.</p>
    </section>
    <section class="ay-detail-section" aria-labelledby="ayDetailTermsTitle">
      <h3 id="ayDetailTermsTitle">Terms</h3>
      ${termItems}
    </section>
    <section class="ay-detail-section ay-drawer-actions" aria-label="Year actions">
      ${detailLink('manage_terms', 'Manage terms', 'bi-calendar3-range')}
      ${detailLink('year_calendar', 'Year calendar', 'bi-calendar2-week')}
      <a class="ay-drawer-link" href="home.php?route=year_rollover"><i class="bi bi-arrow-left-right" aria-hidden="true"></i><span>Year Transition</span></a>
    </section>`;
  },

  bulkRows() {
    const offset = Number(document.getElementById('ayBulkOffset')?.value || 0);
    const selected = this.selectedYears().filter((year) => this.isEditable(year));
    const selectedIds = new Set(selected.map((year) => String(year.id)));
    return selected.map((year) => {
      const start = this.addDays(year.start_date, offset);
      const end = this.addDays(year.end_date, offset);
      const yearNumber = Number(start.slice(0, 4));
      const proposedName = yearNumber ? `${yearNumber}/${yearNumber + 1}` : this.yearName(year);
      const collision = this.state.years.some((other) =>
        !selectedIds.has(String(other.id)) && this.yearLabel(other) === proposedName.toLowerCase()
      );
      const invalid = !start || !end || offset === 0 || end < start || collision;
      const reason = !start || !end
        ? 'Missing dates'
        : end < start
          ? 'Closing would fall before opening'
          : collision
            ? `Year name ${proposedName} already exists`
            : offset === 0
              ? 'Choose a non-zero offset'
              : '';
      return { year, start, end, proposedName, invalid, reason };
    });
  },

  openBulkModal() {
    const rows = this.bulkRows();
    if (!rows.length) return this.notify('Select at least one editable academic year.', 'warning');
    const input = document.getElementById('ayBulkOffset');
    if (input) input.value = '0';
    const result = document.getElementById('ayBulkResult');
    if (result) result.replaceChildren();
    this.renderBulkPreview();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ayBulkModal')).show();
  },

  renderBulkPreview() {
    const rows = this.bulkRows();
    const summary = document.getElementById('ayBulkSummary');
    const preview = document.getElementById('ayBulkPreview');
    const submit = document.getElementById('ayBulkSubmit');
    if (!summary || !preview || !submit) return;

    summary.textContent = `${rows.length} academic ${rows.length === 1 ? 'year' : 'years'} selected. Both dates move by the same number of days, so term length is preserved.`;
    preview.innerHTML = `<div class="ay-table-scroll ay-bulk-scroll" role="region" aria-label="Bulk date preview" tabindex="0">
      <table class="ay-bulk-table">
        <thead><tr><th scope="col">Year</th><th scope="col">Current dates</th><th scope="col">After shift</th><th scope="col">Check</th></tr></thead>
        <tbody>${rows.map((row) => `<tr>
          <th scope="row">${this.esc(this.yearName(row.year))}</th>
          <td>${this.fmtDate(row.year.start_date)} – ${this.fmtDate(row.year.end_date)}</td>
          <td>${row.start ? this.fmtDate(row.start) : '—'} – ${row.end ? this.fmtDate(row.end) : '—'}</td>
          <td>${row.invalid ? `<span class="ay-bulk-issue">${this.esc(row.reason)}</span>` : '<span class="ay-bulk-ok">Ready</span>'}</td>
        </tr>`).join('')}</tbody>
      </table>
    </div>`;
    const blocked = rows.some((row) => row.invalid);
    submit.disabled = blocked;
    submit.textContent = `Apply to ${rows.length} year${rows.length === 1 ? '' : 's'}`;
  },

  async saveBulk(event) {
    event.preventDefault();
    const rows = this.bulkRows();
    if (!rows.length || rows.some((row) => row.invalid)) {
      return this.notify('Resolve the preview issues before applying changes.', 'warning');
    }

    const offset = Number(document.getElementById('ayBulkOffset')?.value || 0);
    const direction = offset > 0 ? `forward by ${offset} day${offset === 1 ? '' : 's'}` : `back by ${Math.abs(offset)} day${Math.abs(offset) === 1 ? '' : 's'}`;
    const confirmed = window.confirmAction
      ? await window.confirmAction('Apply bulk date edit', `Move ${rows.length} academic ${rows.length === 1 ? 'year' : 'years'} ${direction}? This updates opening and closing dates only.`, { confirmText: 'Apply changes' })
      : window.confirm(`Move ${rows.length} academic ${rows.length === 1 ? 'year' : 'years'} ${direction}?`);
    if (!confirmed) return;

    const submit = event.currentTarget.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    const results = [];
    for (const row of rows) {
      try {
        await window.API.academic.updateYear(row.year.id, {
          year_name: row.proposedName,
          start_date: row.start,
          end_date: row.end,
        });
        results.push({ ok: true, row });
      } catch (error) {
        results.push({ ok: false, row, message: error.message || 'Update refused.' });
      }
    }

    const failures = results.filter((result) => !result.ok);
    if (failures.length) {
      const resultBox = document.getElementById('ayBulkResult');
      if (resultBox) {
        resultBox.innerHTML = `<div class="ay-bulk-result" role="alert">
          <strong>${results.length - failures.length} updated; ${failures.length} failed.</strong>
          <ul>${failures.map((failure) => `<li>${this.esc(this.yearName(failure.row.year))}: ${this.esc(failure.message)}</li>`).join('')}</ul>
        </div>`;
      }
      if (submit) submit.disabled = false;
      this.notify(`${failures.length} year${failures.length === 1 ? '' : 's'} could not be updated.`, 'warning');
      return;
    }

    bootstrap.Modal.getInstance(document.getElementById('ayBulkModal'))?.hide();
    this.state.selected.clear();
    this.state.referenceCache = null;
    await this.loadReferencesComposed();
    await this.load();
    this.notify(`${results.length} academic ${results.length === 1 ? 'year' : 'years'} updated.`, 'success');
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;

    document.getElementById('exportYearsBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('ayPrintBtn')?.addEventListener('click', () => this.print());
    document.getElementById('ayBulkBtn')?.addEventListener('click', () => this.openBulkModal());

    document.getElementById('aySelectAll')?.addEventListener('change', (event) => {
      const checked = event.target.checked;
      this.visibleYears().filter((year) => this.isEditable(year)).forEach((year) => {
        if (checked) this.state.selected.add(String(year.id));
        else this.state.selected.delete(String(year.id));
      });
      this.render();
    });

    const page = document.getElementById('academicYearsPage');
    page?.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => {
      this.state.view = button.dataset.view || 'all';
      this.render();
    }));

    document.getElementById('aySearch')?.addEventListener('input', (event) => {
      this.state.search = String(event.target.value || '').trim().toLowerCase();
      this.render();
    });

    const table = document.getElementById('academicYearsTable');
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

    document.getElementById('ayYearsBody')?.addEventListener('change', (event) => {
      const checkbox = event.target.closest?.('[data-select-year]');
      if (!checkbox) return;
      const id = String(checkbox.dataset.selectYear);
      if (checkbox.checked) this.state.selected.add(id);
      else this.state.selected.delete(id);
      this.syncSelectionControls();
    });

    document.getElementById('ayYearsBody')?.addEventListener('click', (event) => {
      const detail = event.target.closest?.('[data-detail-year]');
      if (detail) {
        void this.openDetail(detail.dataset.detailYear);
        return;
      }
      const edit = event.target.closest?.('[data-edit-year]');
      if (edit) {
        this.editYear(edit.dataset.editYear);
        return;
      }
      if (event.target.closest?.('[data-clear-filters]')) {
        this.state.search = '';
        this.state.view = 'all';
        const search = document.getElementById('aySearch');
        if (search) search.value = '';
        this.render();
      }
    });

    const bulkForm = document.getElementById('ayBulkForm');
    bulkForm?.addEventListener('input', (event) => {
      if (event.target.id === 'ayBulkOffset') this.renderBulkPreview();
    });
    bulkForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      void this.saveBulk(event);
    });

    const form = document.getElementById('addAcademicYearForm');
    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      void this.saveYear(event.currentTarget);
    });
    form?.elements.start_date?.addEventListener('change', () => {
      const startYear = Number(String(form.elements.start_date.value || '').slice(0, 4));
      if (startYear) form.elements.year_name.value = `${startYear}/${startYear + 1}`;
    });
  },

  applyPermissions() {
    const auth = window.AuthContext;
    const exportButton = document.getElementById('exportYearsBtn');
    if (exportButton && auth?.canExport && !auth.canExport('academic')) {
      exportButton.disabled = true;
      exportButton.title = 'Your account cannot export academic data.';
    }
    const printButton = document.getElementById('ayPrintBtn');
    if (printButton && auth?.canPrint && !auth.canPrint('academic')) {
      printButton.disabled = true;
      printButton.title = 'Your account cannot print academic data.';
    }
  },

  editYear(id) {
    const year = this.state.years.find((item) => Number(item.id) === Number(id));
    const form = document.getElementById('addAcademicYearForm');
    if (!year || !form || !this.isEditable(year)) return;
    this.state.editingYearId = Number(year.id);
    form.elements.year_name.value = this.yearName(year);
    form.elements.start_date.value = this.day(year.start_date);
    form.elements.end_date.value = this.day(year.end_date);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editYearModal')).show();
  },

  async saveYear(form) {
    if (!this.state.editingYearId) return;
    const payload = Object.fromEntries(new FormData(form).entries());
    if (!payload.year_name.trim() || !payload.start_date || !payload.end_date || payload.end_date < payload.start_date) {
      this.notify('Enter valid opening and closing dates, with closing after opening.', 'warning');
      return;
    }
    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    try {
      await window.API.academic.updateYear(this.state.editingYearId, payload);
      bootstrap.Modal.getInstance(document.getElementById('editYearModal'))?.hide();
      this.state.editingYearId = null;
      await this.load();
      this.notify('Academic year updated.', 'success');
    } catch (error) {
      this.notify(error.message || 'Could not update academic year.', 'danger');
    } finally {
      if (submit) submit.disabled = false;
    }
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) {
      return this.notify('You do not have permission to export academic data.', 'warning');
    }
    if (!window.KingswayFileLifecycle?.exportText) {
      return this.notify('Export is not available in this browser session.', 'warning');
    }
    const rows = this.visibleYears();
    if (!rows.length) return this.notify('There are no academic years to export.', 'warning');
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [
      ['Academic Year', 'Opens', 'Closes', 'Terms', 'Status'],
      ...rows.map((year) => [
        this.yearName(year),
        year.start_date,
        year.end_date,
        year.term_count ?? '',
        this.yearStatus(year).label,
      ]),
    ].map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle.exportText(csv, 'academic_years.csv', 'text/csv;charset=utf-8;');
  },

  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
      return this.notify('You do not have permission to print academic data.', 'warning');
    }
    this.updatePrintContext(this.visibleYears().length);
    window.print();
  },

  notify(message, type = 'info') {
    if (window.showNotification) window.showNotification(message, type);
    else if (window.showToast) window.showToast(message, type);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.readUrlState();
    this.bindEvents();
    this.applyPermissions();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.('academic_years', {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      },
    }, ['academic']);
  },
};

window.academicYearsController = academicYearsController;
var startAcademicYears = () => academicYearsController.init().catch((error) => {
  console.error('Academic years page initialization failed:', error);
  const body = document.getElementById('ayYearsBody');
  if (body) {
    body.innerHTML = '<tr><td colspan="7"><div class="ay-empty"><h3>Page setup failed</h3><p>Refresh to try again.</p></div></td></tr>';
  }
});
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startAcademicYears);
else startAcademicYears();
