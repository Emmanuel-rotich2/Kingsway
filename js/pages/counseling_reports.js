/**
 * Counseling Reports Controller
 * Counseling cases, referrals, and session reports.
 */
const counselingReportsController = {
  state: { students: [], counselors: [], caseTypes: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "counseling-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Counseling Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [students, counselors, types] = await Promise.all([
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/students/get-all', null, { limit: '500', status: 'active' }),
        cache?.counselors !== undefined ? cache.counselors : this.API('GET', '/api/staff/counselors'),
        cache?.caseTypes !== undefined ? cache.caseTypes : this.API('GET', '/api/students/counseling-meta'),
      ]);
      this.state.students = this.norm(students);
      this.state.counselors = this.norm(counselors);
      const caseTypesRaw = this.norm(types);
      const caseTypeList = Array.isArray(caseTypesRaw) ? caseTypesRaw : (caseTypesRaw?.case_types || []);
      this.state.caseTypes = caseTypeList.map(x => typeof x === 'string' ? { id: x, name: x } : x);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const s = document.getElementById('cnStudent');
    const c = document.getElementById('cnCounselor');
    const t = document.getElementById('cnType');
    s.innerHTML = '<option value="">All Students</option>' + this.state.students.map(x => `<option value="${x.id}">${this.esc(x.admission_no || '')} ${this.esc(x.first_name || '')} ${this.esc(x.last_name || '')}</option>`).join('');
    c.innerHTML = '<option value="">All Counselors</option>' + this.state.counselors.map(x => `<option value="${x.id}">${this.esc(x.first_name || '')} ${this.esc(x.last_name || '')}</option>`).join('');
    t.innerHTML = '<option value="">All Types</option>' + this.state.caseTypes.map(x => `<option value="${x.id}">${this.esc(x.name || x.case_type)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('cnStudent')?.value) params.set('student_id', document.getElementById('cnStudent').value);
    if (document.getElementById('cnCounselor')?.value) params.set('counselor_id', document.getElementById('cnCounselor').value);
    if (document.getElementById('cnType')?.value) params.set('case_type', document.getElementById('cnType').value);

    try {
      const response = await this.API('GET', '/api/reports/counseling', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('cnBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No counseling records.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.student_name || '—')}</td>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small">${this.esc(x.counselor_name || '—')}</td>
      <td class="small">${this.esc(x.case_type || '—')}</td>
      <td class="small">${this.esc(x.session_date || '—')}</td>
      <td class="small">${this.esc(x.status || '—')}</td>
      <td class="small">${this.esc(x.notes || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="counselingReportsController.viewDetails(${x.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "counseling-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Counseling Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("counseling_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "counseling"]);
  },
};

window.counselingReportsController = counselingReportsController;
document.addEventListener('DOMContentLoaded', () => counselingReportsController.init().catch(() => {}));