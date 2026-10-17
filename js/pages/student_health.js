/**
 * Student Health Controller
 * Manages student health records and medical information.
 */
const studentHealthController = {
  state: { referenceCache: null, records: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-health-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Health Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [records, student] = await Promise.all([
        cache?.records !== undefined ? cache.records : this.API('GET', `/api/students/${studentId}/health`),
        cache?.student !== undefined ? cache.student : this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.records = Array.isArray(records?.data) ? records.data : (Array.isArray(records) ? records : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const records = this.state.records;
    const student = this.state.student;
    document.getElementById('shStudentName')?.textContent = student?.name || '—';
    document.getElementById('shAdmissionNo')?.textContent = student?.admission_number || '—';
    const body = document.getElementById('shRecordsBody');
    if (!records.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No health records.</td></tr>'; return; }
    body.innerHTML = records.map(r => `<tr>
      <td class="small">${this.fmtDate(r.date)}</td>
      <td class="small">${this.esc(r.category || '—')}</td>
      <td class="small">${this.esc(r.description || '—')}</td>
      <td class="small"><span class="badge bg-${r.severity === 'high' ? 'danger' : r.severity === 'medium' ? 'warning' : 'info'}">${this.esc(r.severity || '—')}</span></td>
      <td class="small">${this.esc(r.recorded_by || '—')}</td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.loadReferencesComposed();
    await this.load(studentId);
    window.APIRealtime?.register?.("student_health", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
    }, ["students", "health"]);
  },
};

window.studentHealthController = studentHealthController;
document.addEventListener('DOMContentLoaded', () => studentHealthController.init().catch(() => {}));