/**
 * Student Context Controller
 * Provides contextual student information for various views.
 */
const studentContextController = {
  state: { referenceCache: null, context: null, student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },

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
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };
    setText('scStudentName', student?.name || '—');
    setText('scAdmissionNo', student?.admission_number || '—');
    setText('scClass', context?.class_name || '—');
    setText('scStream', context?.stream_name || '—');
    setText('scStudentType', context?.student_type || '—');
    setText('scDormitory', context?.dormitory || 'Day Scholar');
    setText('scParent', context?.parent_name || '—');
    setText('scContact', context?.parent_phone || '—');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.load(studentId);
    window.APIRealtime?.register?.("student_context", {
      refresh: async () => { this.state.referenceCache = null; await this.load(studentId); }
    }, ["students"]);
  },
};

window.studentContextController = studentContextController;
document.addEventListener('DOMContentLoaded', () => studentContextController.init().catch(() => {}));