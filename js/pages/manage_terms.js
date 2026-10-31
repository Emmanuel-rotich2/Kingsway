/**
 * Manage Terms Controller
 * Manages academic terms.
 */
const manageTermsController = {
  state: { referenceCache: null, terms: [], years: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },

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
    if (term?.is_current === true || Number(term?.is_current) === 1 || status === 'current') return 'current';
    if (status === 'active') return 'active';
    if (status === 'completed' || status === 'closed') return 'completed';
    if (status === 'upcoming' || status === 'planned') return 'upcoming';
    return status;
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "academic-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Manage Terms Node view failed; using direct reads:", error);
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
      if (!normalizedTerms || !normalizedYears) throw new Error('The academic API returned an unexpected terms or years data structure.');
      this.state.terms = normalizedTerms;
      this.state.years = normalizedYears;
      this.renderTerms();
      this.renderFormOptions();
      this.renderStats();
      this.bindFilters();
    } catch (e) {
      console.error('Failed to load terms:', e);
      const body = document.getElementById('mtTermsBody');
      if (body) body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Could not load terms. Check your connection and refresh.</td></tr>';
    }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderTerms() {
    const terms = this.filteredTerms();
    const body = document.getElementById('mtTermsBody');
    if (!body) return;
    // Keep the summary tied to the same loaded source as the table, including
    // after a filter refresh or a realtime update.
    this.renderStats();
    if (!terms.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No terms defined.</td></tr>'; return; }
    body.innerHTML = terms.map((t, index) => `<tr>
      <td class="small">${index + 1}</td>
      <td class="small">${this.esc(t.name || t.term_name || '—')}</td>
      <td class="small">${this.esc(t.year_name || t.academic_year_name || t.year || '—')}</td>
      <td class="small">${this.fmtDate(t.start_date)}</td>
      <td class="small">${this.fmtDate(t.end_date)}</td>
      <td class="small">${Number(t.weeks ?? t.week_count ?? 0) || '—'}</td>
      <td class="small"><span class="badge bg-${t.is_current || t.status === 'current' ? 'success' : t.is_active || t.status === 'active' ? 'primary' : 'secondary'}">${this.esc(t.status || (t.is_current ? 'Current' : t.is_active ? 'Active' : 'Inactive'))}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" type="button" title="Edit term" onclick="ManageTermsController.editTerm(${Number(t.id)})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  filteredTerms() {
    const yearId = document.getElementById('yearFilter')?.value || '';
    const status = document.getElementById('filterSelect')?.value || '';
    const search = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const date = document.getElementById('dateFilter')?.value || '';
    return this.state.terms.filter((term) => {
      const matchesYear = !yearId || String(term.academic_year_id ?? term.year) === yearId;
      const normalizedStatus = this.statusOf(term);
      const matchesStatus = !status || normalizedStatus === status;
      const matchesSearch = !search || `${term.name || ''} ${term.year_name || ''} ${term.year_code || ''}`.toLowerCase().includes(search);
      const matchesDate = !date || (String(term.start_date || '') <= date && String(term.end_date || '') >= date);
      return matchesYear && matchesStatus && matchesSearch && matchesDate;
    });
  },

  renderStats() {
    const statuses = this.state.terms.map((term) => this.statusOf(term));
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = String(value); };
    set('mtStatTotal', this.state.terms.length);
    set('mtStatActive', statuses.filter((status) => status === 'current' || status === 'active').length);
    set('mtStatCompleted', statuses.filter((status) => status === 'completed').length);
    set('mtStatUpcoming', statuses.filter((status) => status === 'upcoming').length);
  },

  bindFilters() {
    if (this.filtersBound) return;
    this.filtersBound = true;
    const yearFilter = document.getElementById('yearFilter');
    if (yearFilter) yearFilter.innerHTML = '<option value="">All Academic Years</option>' + this.state.years.map((year) => `<option value="${Number(year.id)}">${this.esc(year.year_name || year.name || year.year_code)}</option>`).join('');
    const statusFilter = document.getElementById('filterSelect');
    if (statusFilter) statusFilter.innerHTML = '<option value="">All Statuses</option><option value="current">Current</option><option value="completed">Completed</option><option value="upcoming">Upcoming</option>';
    ['yearFilter', 'filterSelect', 'searchInput', 'dateFilter'].forEach((id) => document.getElementById(id)?.addEventListener(id === 'searchInput' ? 'input' : 'change', () => this.renderTerms()));
  },

  renderFormOptions() {
    const years = this.state.years;
    const yearSelect = document.getElementById('mtYearSelect');
    const target = yearSelect || document.getElementById('recordYear');
    if (target) target.innerHTML = '<option value="">Select Year</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name || y.year_name || y.year_code)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  refresh() { return this.load(); },

  showAddModal() {
    document.getElementById('recordForm')?.reset();
    document.getElementById('recordId').value = '';
    document.getElementById('formModalTitle').innerHTML = '<i class="bi bi-list-ul-ol me-2"></i>Add Term';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
  },

  editTerm(id) {
    const term = this.state.terms.find((item) => Number(item.id) === Number(id));
    if (!term) return;
    document.getElementById('recordId').value = term.id;
    document.getElementById('recordName').value = term.name || '';
    document.getElementById('recordYear').value = term.academic_year_id ?? term.year ?? '';
    document.getElementById('recordStartDate').value = term.start_date || '';
    document.getElementById('recordEndDate').value = term.end_date || '';
    document.getElementById('recordHalfTermStart').value = term.half_term_start || '';
    document.getElementById('recordHalfTermEnd').value = term.half_term_end || '';
    document.getElementById('recordStatus').value = term.status || 'upcoming';
    document.getElementById('formModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Term';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
  },

  async saveRecord() {
    const id = document.getElementById('recordId')?.value;
    const name = document.getElementById('recordName')?.value.trim();
    const payload = {
      name,
      academic_year_id: Number(document.getElementById('recordYear')?.value || 0),
      start_date: document.getElementById('recordStartDate')?.value,
      end_date: document.getElementById('recordEndDate')?.value,
      half_term_start: document.getElementById('recordHalfTermStart')?.value || null,
      half_term_end: document.getElementById('recordHalfTermEnd')?.value || null,
      status: document.getElementById('recordStatus')?.value,
    };
    if (!name || !payload.academic_year_id || !payload.start_date || !payload.end_date) {
      this.notify('Term name, academic year, opening date and closing date are required.', 'warning');
      return;
    }
    const termNumber = Number(name.match(/term\s*(\d+)/i)?.[1] || 1);
    try {
      if (id) {
        await this.API('PUT', `/api/academic/terms/${Number(id)}`, payload);
      } else {
        await this.API('POST', '/api/academic/terms', { ...payload, term_number: termNumber });
      }
      bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();
      this.notify(id ? 'Term updated.' : 'Term created.', 'success');
      await this.load();
    } catch (error) {
      this.notify(error.message || 'Could not save term.', 'danger');
    }
  },

  exportCSV() {
    const rows = this.filteredTerms();
    if (!rows.length) { this.notify('There are no terms to export.', 'warning'); return; }
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Term', 'Academic Year', 'Start Date', 'End Date', 'Weeks', 'Status'], ...rows.map((term) => [term.name, term.year_name || term.academic_year_name || term.year, term.start_date, term.end_date, term.weeks, term.status])]
      .map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'academic_terms.csv', 'text/csv;charset=utf-8;');
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("manage_terms", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.manageTermsController = manageTermsController;
window.ManageTermsController = manageTermsController;
document.addEventListener('DOMContentLoaded', () => manageTermsController.init().catch((error) => {
  console.error('Manage terms page initialization failed:', error);
  const body = document.getElementById('mtTermsBody');
  if (body) body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Page setup failed. Refresh to try again.</td></tr>';
}));
