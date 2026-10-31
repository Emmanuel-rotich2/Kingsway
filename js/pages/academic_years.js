/**
 * Academic Years Controller
 * Manages academic year definitions.
 */
const academicYearsController = {
  state: { referenceCache: null, years: [], termCountsAvailable: false, view: 'all', editingYearId: null, eventsBound: false },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },

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

  yearLabel(year) { return String(year?.year_name || year?.name || year?.year_code || '').trim().toLowerCase(); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "manager-overview");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Academic Years Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const years = cache?.years !== undefined ? cache.years : await this.API('GET', '/api/academic/years/list');
      this.state.years = this.listFrom(years) || [];
      await this.loadTermCounts();
      this.render();
      await this.loadCurrentContext();
    } catch (e) {
      console.error('Failed to load academic years:', e);
      const body = document.getElementById('ayYearsBody');
      if (body) body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Could not load academic years. Check your connection and refresh.</td></tr>';
      const yearBox = document.getElementById('currentYearInfo');
      const termBox = document.getElementById('currentTermInfo');
      if (yearBox) yearBox.innerHTML = '<p class="text-danger mb-0">Could not load academic year details.</p>';
      if (termBox) termBox.innerHTML = '<p class="text-danger mb-0">Could not load current term details.</p>';
    }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  async loadTermCounts() {
    this.state.termCountsAvailable = false;
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
        if (Number.isInteger(yearId) && yearId > 0) counts.set(yearId, (counts.get(yearId) || 0) + 1);
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

  async loadCurrentContext() {
    const yearBox = document.getElementById('currentYearInfo');
    const termBox = document.getElementById('currentTermInfo');
    const currentYear = this.state.years.find((year) =>
      year.is_current === true || Number(year.is_current) === 1 ||
      ['current', 'active'].includes(String(year.status || year.year_status || '').trim().toLowerCase())
    ) || null;
    if (!currentYear) {
      if (yearBox) yearBox.innerHTML = '<p class="text-muted mb-0">No current academic year is configured.</p>';
      if (termBox) termBox.innerHTML = '<p class="text-muted mb-0">No current term is configured.</p>';
      return;
    }
    if (yearBox) yearBox.innerHTML = `<h4 class="mb-1">${this.esc(currentYear.year_name || currentYear.name || currentYear.year_code)}</h4><div class="text-muted">${this.fmtDate(currentYear.start_date)} – ${this.fmtDate(currentYear.end_date)}</div>`;
    try {
      const response = await window.API.academic.listTerms({ academic_year_id: currentYear.id });
      const terms = this.listFrom(response) || [];
      const term = terms.find((item) =>
        item.is_current === true || Number(item.is_current) === 1 ||
        String(item.status || '').trim().toLowerCase() === 'current'
      ) || null;
      if (termBox) termBox.innerHTML = term
        ? `<h5 class="mb-1">${this.esc(term.name || term.term_name || 'Current term')}</h5><div class="text-muted">${this.fmtDate(term.start_date)} – ${this.fmtDate(term.end_date)}</div>`
        : '<p class="text-muted mb-0">No current term is configured for this academic year.</p>';
    } catch (error) {
      console.error('Failed to load current term:', error);
      if (termBox) termBox.innerHTML = '<p class="text-danger mb-0">Could not load current term details.</p>';
    }
  },

  render() {
    const years = this.state.years.filter((year) => {
      if (this.state.view === 'all') return true;
      const status = String(year.status || year.year_status || '').trim().toLowerCase();
      const current = year.is_current === true || Number(year.is_current) === 1;
      const active = current || ['active', 'registration', 'closing'].includes(status);
      const past = status === 'archived' || status === 'completed' || (year.end_date && String(year.end_date).slice(0, 10) < this.todayLocal());
      return this.state.view === 'active' ? active : past;
    });
    const body = document.getElementById('ayYearsBody');
    if (!body) return;
    if (!years.length) { body.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No ${this.state.view === 'all' ? '' : this.state.view + ' '}academic years found.</td></tr>`; return; }
    body.innerHTML = years.map(y => `<tr>
      <td class="small">${this.esc(y.name || y.year_name || y.year_code || '—')}</td>
      <td class="small">${this.fmtDate(y.start_date)}</td>
      <td class="small">${this.fmtDate(y.end_date)}</td>
      <td class="small">${this.state.termCountsAvailable && y.term_count != null ? Number(y.term_count) : '<span class="text-muted" title="Term counts could not be matched to this academic year">—</span>'}</td>
      <td class="small"><span class="badge bg-${this.yearStatus(y).className}">${this.yearStatus(y).label}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="academicYearsController.editYear(${y.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  yearStatus(year) {
    const status = String(year.status || year.year_status || '').trim().toLowerCase();
    if (year.is_current === true || Number(year.is_current) === 1) return { label: 'Current', className: 'success' };
    if (['active', 'registration', 'closing'].includes(status) || year.is_active === true || Number(year.is_active) === 1) return { label: 'Active', className: 'primary' };
    return { label: status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Inactive', className: 'secondary' };
  },

  todayLocal() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    document.getElementById('exportYearsBtn')?.addEventListener('click', () => this.exportCSV());
    const page = document.getElementById('academicYearsPage');
    page?.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => {
      this.state.view = button.dataset.view || 'all';
      page.querySelectorAll('[data-view]').forEach((item) => item.classList.toggle('active', item === button));
      this.render();
    }));
    document.getElementById('addAcademicYearForm')?.addEventListener('submit', (event) => {
      event.preventDefault();
      void this.saveYear(event.currentTarget);
    });
    const form = document.getElementById('addAcademicYearForm');
    form?.elements.start_date?.addEventListener('change', () => {
      const startYear = Number(String(form.elements.start_date.value || '').slice(0, 4));
      if (startYear) form.elements.year_name.value = `${startYear}/${startYear + 1}`;
    });
  },

  editYear(id) {
    const year = this.state.years.find((item) => Number(item.id) === Number(id));
    const form = document.getElementById('addAcademicYearForm');
    if (!year || !form) return;
    this.state.editingYearId = Number(year.id);
    form.elements.year_name.value = year.year_name || year.name || year.year_code || '';
    form.elements.start_date.value = String(year.start_date || '').slice(0, 10);
    form.elements.end_date.value = String(year.end_date || '').slice(0, 10);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editYearModal')).show();
  },

  async saveYear(form) {
    if (!this.state.editingYearId) return;
    const payload = Object.fromEntries(new FormData(form).entries());
    if (!payload.year_name.trim() || !payload.start_date || !payload.end_date || payload.end_date < payload.start_date) {
      this.notify('Enter a year name and valid opening and closing dates.', 'warning');
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
    const rows = this.state.years;
    if (!rows.length) return this.notify('There are no academic years to export.', 'warning');
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Academic Year', 'Start Date', 'End Date', 'Terms', 'Status'], ...rows.map((year) => [year.year_name || year.name || year.year_code, year.start_date, year.end_date, year.term_count ?? '', this.yearStatus(year).label])]
      .map((row) => row.map(cell).join(',')).join('\r\n');
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) return this.notify('You do not have permission to export academic data.', 'warning');
    window.KingswayFileLifecycle?.exportText?.(csv, 'academic_years.csv', 'text/csv;charset=utf-8;');
  },

  notify(message, type = 'info') {
    if (window.showNotification) window.showNotification(message, type);
    else if (window.showToast) window.showToast(message, type);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.bindEvents();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("academic_years", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.academicYearsController = academicYearsController;
document.addEventListener('DOMContentLoaded', () => academicYearsController.init().catch((error) => {
  console.error('Academic years page initialization failed:', error);
  const body = document.getElementById('ayYearsBody');
  if (body) body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Page setup failed. Refresh to try again.</td></tr>';
}));
