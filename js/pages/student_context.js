/**
 * Student Context Controller
 * Provides contextual student information for various views.
 */
const studentContextController = {
  state: { referenceCache: null, context: null, student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-portfolio-roster");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Context Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [context, student] = await Promise.all([
        cache?.context !== undefined ? cache.context : this.API('GET', `/api/students/${studentId}/context`),
        cache?.student !== undefined ? cache.student : this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.context = context?.data || context || {};
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  render() {
    const context = this.state.context;
    const student = this.state.student;
    document.getElementById('scStudentName')?.textContent = student?.name || '—';
    document.getElementById('scAdmissionNo')?.textContent = student?.admission_number || '—';
    document.getElementById('scClass')?.textContent = context?.class_name || '—';
    document.getElementById('scStream')?.textContent = context?.stream_name || '—';
    document.getElementById('scStudentType')?.textContent = context?.student_type || '—';
    document.getElementById('scDormitory')?.textContent = context?.dormitory || 'Day Scholar';
    document.getElementById('scParent')?.textContent = context?.parent_name || '—';
    document.getElementById('scContact')?.textContent = context?.parent_phone || '—';
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.loadReferencesComposed();
    await this.load(studentId);
    window.APIRealtime?.register?.("student_context", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
    }, ["students"]);
  },
};

window.studentContextController = studentContextController;
document.addEventListener('DOMContentLoaded', () => studentContextController.init().catch(() => {}));