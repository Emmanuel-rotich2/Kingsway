/**
 * Exception Reports Controller
 * System exceptions, errors, and anomaly reports.
 */
const exceptionReportsController = {
  state: { exceptionTypes: [], students: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "exception-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Exception Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [types, students] = await Promise.all([
        cache?.exceptionTypes !== undefined ? cache.exceptionTypes : this.API('GET', '/api/finance/exception-reports'),
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/students/get-all', null, { limit: '500', status: 'active' }),
      ]);
      this.state.exceptionTypes = this.norm(types);
      this.state.students = this.norm(students);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const t = document.getElementById('exType');
    const s = document.getElementById('exStudent');
    t.innerHTML = '<option value="">All Types</option>' + this.state.exceptionTypes.map(x => `<option value="${x.id}">${this.esc(x.name || x.exception_type)}</option>`).join('');
    s.innerHTML = '<option value="">All Students</option>' + this.state.students.map(x => `<option value="${x.id}">${this.esc(x.admission_no || '')} ${this.esc(x.first_name || '')} ${this.esc(x.last_name || '')}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('exType')?.value) params.set('type_id', document.getElementById('exType').value);
    if (document.getElementById('exStudent')?.value) params.set('student_id', document.getElementById('exStudent').value);

    try {
      const response = await this.API('GET', '/api/reports/exceptions', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('exBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No exception records.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.exception_type || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.student_name || '—')}</td>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small">${this.esc(x.description || '—')}</td>
      <td class="small">${this.esc(x.occurred_at || '—')}</td>
      <td class="small"><span class="badge bg-${x.severity === 'critical' ? 'danger' : x.severity === 'high' ? 'warning' : 'info'}">${this.esc(x.severity || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="exceptionReportsController.viewDetails(${x.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "exception-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Exception Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("exception_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "system"]);
  },
};

window.exceptionReportsController = exceptionReportsController;
document.addEventListener('DOMContentLoaded', () => exceptionReportsController.init().catch(() => {}));