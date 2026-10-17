/**
 * Student Subject Performance Controller
 * Detailed subject-wise performance for a student.
 */
const studentSubjectPerformanceController = {
  state: { referenceCache: null, performance: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-subject-performance-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Subject Performance Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [performance, student] = await Promise.all([
        cache?.performance !== undefined ? cache.performance : this.API('GET', `/api/students/${studentId}/subject-performance`),
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
    document.getElementById('sspStudentName')?.textContent = student?.name || '—';
    document.getElementById('sspAdmissionNo')?.textContent = student?.admission_number || '—';
    const body = document.getElementById('sspPerformanceBody');
    if (!performance.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No subject performance data.</td></tr>'; return; }
    body.innerHTML = performance.map(p => `<tr>
      <td class="small">${this.esc(p.subject || '—')}</td>
      <td class="small">${this.esc(p.term || '—')}</td>
      <td class="small text-center">${this.esc(p.ca_score || '—')}</td>
      <td class="small text-center">${this.esc(p.exam_score || '—')}</td>
      <td class="small text-center fw-bold">${this.esc(p.total_score || '—')}</td>
      <td class="small text-center"><span class="badge bg-${p.grade >= 70 ? 'success' : p.grade >= 50 ? 'warning' : 'danger'}">${this.esc(p.grade || '—')}</span></td>
    </tr>`).join('');
    this.renderFilters();
  },

  renderFilters() {
    const terms = this.getCache('terms') || [];
    const subjects = this.getCache('subjects') || [];
    const termSelect = document.getElementById('sspTermFilter');
    const subjectSelect = document.getElementById('sspSubjectFilter');
    if (termSelect) termSelect.innerHTML = '<option value="">All Terms</option>' + terms.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">All Subjects</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.loadReferencesComposed();
    await this.load(studentId);
    window.APIRealtime?.register?.("student_subject_performance", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
    }, ["students", "academic"]);
  },
};

window.studentSubjectPerformanceController = studentSubjectPerformanceController;
document.addEventListener('DOMContentLoaded', () => studentSubjectPerformanceController.init().catch(() => {}));