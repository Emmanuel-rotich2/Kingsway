/**
 * Manage Terms Controller
 * Manages academic terms.
 */
const manageTermsController = {
  state: { referenceCache: null, terms: [], years: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

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
      this.state.terms = Array.isArray(terms?.data) ? terms.data : (Array.isArray(terms) ? terms : []);
      this.state.years = Array.isArray(years?.data) ? years.data : (Array.isArray(years) ? years : []);
      this.renderTerms();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderTerms() {
    const terms = this.state.terms;
    const body = document.getElementById('mtTermsBody');
    if (!terms.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No terms defined.</td></tr>'; return; }
    body.innerHTML = terms.map(t => `<tr>
      <td class="small">${this.esc(t.name || '—')}</td>
      <td class="small">${this.esc(t.year_name || '—')}</td>
      <td class="small">${this.fmtDate(t.start_date)}</td>
      <td class="small">${this.fmtDate(t.end_date)}</td>
      <td class="small"><span class="badge bg-${t.is_current ? 'success' : t.is_active ? 'primary' : 'secondary'}">${t.is_current ? 'Current' : t.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="manageTermsController.editTerm(${t.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const years = this.state.years;
    const yearSelect = document.getElementById('mtYearSelect');
    if (yearSelect) yearSelect.innerHTML = '<option value="">Select Year</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async createTerm() {
    const name = document.getElementById('mtName')?.value;
    const yearId = document.getElementById('mtYearSelect')?.value;
    const startDate = document.getElementById('mtStartDate')?.value;
    const endDate = document.getElementById('mtEndDate')?.value;
    if (!name || !yearId || !startDate || !endDate) { this.notify('All fields required', 'warning'); return; }
    try {
      await this.API('POST', '/api/academic/terms', { name, year_id: yearId, start_date: startDate, end_date: endDate });
      this.notify('Term created', 'success');
      document.getElementById('mtForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Creation failed', 'danger'); }
  },

  async editTerm(id) { /* open edit modal */ },

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
document.addEventListener('DOMContentLoaded', () => manageTermsController.init().catch(() => {}));