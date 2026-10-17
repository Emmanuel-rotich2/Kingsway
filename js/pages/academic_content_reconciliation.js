/**
 * Academic Content Reconciliation Controller
 * Reconciles academic content across terms and years.
 */
const academicContentReconciliationController = {
  state: { referenceCache: null, discrepancies: [], years: [], classes: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "academic-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Academic Content Reconciliation Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [discrepancies, years, classes] = await Promise.all([
        cache?.discrepancies !== undefined ? cache.discrepancies : this.API('GET', '/api/academic/content-reconciliation'),
        cache?.years !== undefined ? cache.years : this.API('GET', '/api/academic/years/list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
      ]);
      this.state.discrepancies = Array.isArray(discrepancies?.data) ? discrepancies.data : (Array.isArray(discrepancies) ? discrepancies : []);
      this.state.years = Array.isArray(years?.data) ? years.data : (Array.isArray(years) ? years : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.renderDiscrepancies();
      this.renderFilters();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderDiscrepancies() {
    const discrepancies = this.state.discrepancies;
    const body = document.getElementById('acrDiscrepanciesBody');
    if (!discrepancies.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No discrepancies found.</td></tr>'; return; }
    body.innerHTML = discrepancies.map(d => `<tr>
      <td class="small">${this.esc(d.year || '—')}</td>
      <td class="small">${this.esc(d.class_name || '—')}</td>
      <td class="small">${this.esc(d.subject || '—')}</td>
      <td class="small"><span class="badge bg-${d.severity === 'high' ? 'danger' : d.severity === 'medium' ? 'warning' : 'info'}">${this.esc(d.severity || '—')}</span></td>
      <td class="small">${this.esc(d.description || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="academicContentReconciliationController.resolve(${d.id})"><i class="bi bi-check-circle"></i></button></td>
    </tr>`).join('');
  },

  renderFilters() {
    const years = this.state.years;
    const classes = this.state.classes;
    const yearSelect = document.getElementById('acrYearFilter');
    const classSelect = document.getElementById('acrClassFilter');
    if (yearSelect) yearSelect.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name)}</option>`).join('');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
  },

  async resolve(id) {
    try {
      await this.API('POST', `/api/academic/content-reconciliation/${id}/resolve`);
      this.notify('Discrepancy resolved', 'success');
      await this.load();
    } catch (e) { this.notify('Resolution failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("academic_content_reconciliation", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.academicContentReconciliationController = academicContentReconciliationController;
document.addEventListener('DOMContentLoaded', () => academicContentReconciliationController.init().catch(() => {}));