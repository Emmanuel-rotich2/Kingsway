/**
 * Student Schedule Extension Controller
 * Manages extended schedule arrangements for students.
 */
const studentScheduleExtensionController = {
  state: { referenceCache: null, extensions: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    this.state.referenceCache = {};
  },

  async load(studentId) {
    try {
      const [extensions, student] = await Promise.all([
        this.API('GET', `/api/students/${studentId}/schedule-extensions`),
        this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.extensions = Array.isArray(extensions?.data) ? extensions.data : (Array.isArray(extensions) ? extensions : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const extensions = this.state.extensions;
    const student = this.state.student;
    document.getElementById('sseStudentName')?.textContent = student?.name || '—';
    const body = document.getElementById('sseExtensionsBody');
    if (!extensions.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No schedule extensions.</td></tr>'; return; }
    body.innerHTML = extensions.map(e => `<tr>
      <td class="small">${this.esc(e.subject || '—')}</td>
      <td class="small">${this.fmtDate(e.start_date)}</td>
      <td class="small">${this.fmtDate(e.end_date)}</td>
      <td class="small"><span class="badge bg-${e.status === 'active' ? 'success' : e.status === 'pending' ? 'warning' : 'secondary'}">${this.esc(e.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="studentScheduleExtensionController.viewDetails(${e.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async viewDetails(id) { /* open detail modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.load(studentId);
    window.APIRealtime?.register?.("student_schedule_extension", {
      refresh: async () => await this.load(studentId)
    }, ["students", "academic"]);
  },
};

window.studentScheduleExtensionController = studentScheduleExtensionController;
document.addEventListener('DOMContentLoaded', () => studentScheduleExtensionController.init().catch(() => {}));