/**
 * Student Counseling Controller
 * Manages student counseling sessions and records.
 */
const studentCounselingController = {
  state: { referenceCache: null, sessions: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    this.state.referenceCache = {};
  },

  async load(studentId) {
    try {
      const [sessions, student] = await Promise.all([
        this.API('GET', `/api/students/${studentId}/counseling`),
        this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.sessions = Array.isArray(sessions?.data) ? sessions.data : (Array.isArray(sessions) ? sessions : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const sessions = this.state.sessions;
    const student = this.state.student;
    document.getElementById('scStudentName')?.textContent = student?.name || '—';
    const body = document.getElementById('scSessionsBody');
    if (!sessions.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No counseling sessions.</td></tr>'; return; }
    body.innerHTML = sessions.map(s => `<tr>
      <td class="small">${this.fmtDate(s.date)}</td>
      <td class="small">${this.esc(s.counselor || '—')}</td>
      <td class="small">${this.esc(s.type || '—')}</td>
      <td class="small">${this.esc(s.summary || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="studentCounselingController.viewNotes(${s.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async viewNotes(id) { /* open session notes modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.load(studentId);
    window.APIRealtime?.register?.("student_counseling", {
      refresh: async () => await this.load(studentId)
    }, ["students", "counseling"]);
  },
};

window.studentCounselingController = studentCounselingController;
document.addEventListener('DOMContentLoaded', () => studentCounselingController.init().catch(() => {}));