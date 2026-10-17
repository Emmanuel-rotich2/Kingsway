/**
 * Exeat Reports Controller
 * Student exeat/leave reports and tracking.
 */
const exeatReportsController = {
  state: { students: [], exeatTypes: [], statuses: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "exeat-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Exeat Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [students, types, statuses] = await Promise.all([
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/students/get-all', null, { limit: '500', status: 'active' }),
        cache?.exeatTypes !== undefined ? cache.exeatTypes : this.API('GET', '/api/attendance/permission-types'),
        cache?.statuses !== undefined ? cache.statuses : this.API('GET', '/api/students/boarding-meta'),
      ]);
      const exeatTypesRaw = this.norm(types);
      const statusesRaw = this.norm(statuses);
      const exeatTypeList = Array.isArray(exeatTypesRaw) ? exeatTypesRaw : (exeatTypesRaw?.permission_types || []);
      const statusList = Array.isArray(statusesRaw) ? statusesRaw : (statusesRaw?.exeat_statuses || []);
      this.state.students = this.norm(students);
      this.state.exeatTypes = exeatTypeList.map(x => typeof x === 'string' ? { id: x, name: x } : x);
      this.state.statuses = statusList.map(x => typeof x === 'string' ? { id: x, name: x } : x);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const s = document.getElementById('exStudent');
    const t = document.getElementById('exType');
    const st = document.getElementById('exStatus');
    s.innerHTML = '<option value="">All Students</option>' + this.state.students.map(x => `<option value="${x.id}">${this.esc(x.admission_no || '')} ${this.esc(x.first_name || '')} ${this.esc(x.last_name || '')}</option>`).join('');
    t.innerHTML = '<option value="">All Types</option>' + this.state.exeatTypes.map(x => `<option value="${x.id}">${this.esc(x.name || x.exeat_type)}</option>`).join('');
    st.innerHTML = '<option value="">All Statuses</option>' + this.state.statuses.map(x => `<option value="${x.id}">${this.esc(x.name || x.status)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('exStudent')?.value) params.set('student_id', document.getElementById('exStudent').value);
    if (document.getElementById('exType')?.value) params.set('type_id', document.getElementById('exType').value);
    if (document.getElementById('exStatus')?.value) params.set('status_id', document.getElementById('exStatus').value);

    try {
      const response = await this.API('GET', '/api/reports/exeats', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('exBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No exeat records.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.full_name || '—')}</td>
      <td class="small">${this.esc(x.exeat_type || '—')}</td>
      <td class="small">${this.esc(x.leave_at || '—')}</td>
      <td class="small">${this.esc(x.expected_return || '—')}</td>
      <td class="small"><span class="badge bg-${x.status === 'approved' ? 'success' : x.status === 'pending' ? 'warning' : 'danger'}">${this.esc(x.status || '—')}</span></td>
      <td class="small">${this.esc(x.destination || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="exeatReportsController.viewDetails(${x.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "exeat-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Exeat Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("exeat_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "students"]);
  },
};

window.exeatReportsController = exeatReportsController;
document.addEventListener('DOMContentLoaded', () => exeatReportsController.init().catch(() => {}));