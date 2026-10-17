/**
 * Student Performance Controller
 * Overview of student academic performance.
 */
const studentPerformanceController = {
  state: { referenceCache: null, performance: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-performance-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Performance Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [performance, student] = await Promise.all([
        cache?.performance !== undefined ? cache.performance : this.API('GET', `/api/students/${studentId}/performance`),
        cache?.student !== undefined ? cache.student : this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.performance = Array.isArray(performance?.data) ? performance.data : (Array.isArray(performance) ? performance : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  render() {
    const performance = this.state.performance;
    const student = this.state.student;
    document.getElementById('spStudentName')?.textContent = student?.name || '—';
    document.getElementById('spAdmissionNo')?.textContent = student?.admission_number || '—';
    const body = document.getElementById('spPerformanceBody');
    if (!performance.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No performance data.</td></tr>'; return; }
    body.innerHTML = performance.map(p => `<tr>
      <td class="small">${this.esc(p.term || '—')}</td>
      <td class="small">${this.esc(p.subject || '—')}</td>
      <td class="small text-center">${this.esc(p.score || '—')}</td>
      <td class="small text-center">${this.esc(p.grade || '—')}</td>
      <td class="small">${this.esc(p.teacher || '—')}</td>
    </tr>`).join('');
    this.renderTermFilter();
  },

  renderTermFilter() {
    const terms = this.getCache('terms') || [];
    const select = document.getElementById('spTermFilter');
    if (select) select.innerHTML = '<option value="">All Terms</option>' + terms.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.loadReferencesComposed();
    await this.load(studentId);
    window.APIRealtime?.register?.("student_performance", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
    }, ["students", "academic"]);
  },
};

window.studentPerformanceController = studentPerformanceController;
document.addEventListener('DOMContentLoaded', () => studentPerformanceController.init().catch(() => {}));