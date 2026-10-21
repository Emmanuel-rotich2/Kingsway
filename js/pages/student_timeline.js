/**
 * Student Timeline Controller
 * Displays chronological student events and milestones.
 */
const studentTimelineController = {
  state: { referenceCache: null, timeline: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [timeline, student] = await Promise.all([
        cache?.timeline !== undefined ? cache.timeline : this.API('GET', `/api/students/${studentId}/timeline`),
        cache?.student !== undefined ? cache.student : this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.timeline = Array.isArray(timeline?.data) ? timeline.data : (Array.isArray(timeline) ? timeline : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const timeline = this.state.timeline;
    const student = this.state.student;
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };
    setText('stStudentName', student?.name || '—');
    setText('stAdmissionNo', student?.admission_number || '—');
    const body = document.getElementById('stTimelineBody');
    if (!body) return;
    if (!timeline.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No timeline events.</td></tr>'; return; }
    body.innerHTML = timeline.map(e => `<tr>
      <td class="small">${this.fmtDate(e.date)}</td>
      <td class="small fw-semibold">${this.esc(e.type || '—')}</td>
      <td class="small">${this.esc(e.description || '—')}</td>
      <td class="small text-muted">${this.esc(e.recorded_by || '—')}</td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.load(studentId);
    window.APIRealtime?.register?.("student_timeline", {
      refresh: async () => { this.state.referenceCache = null; await this.load(studentId); }
    }, ["students"]);
  },
};

window.studentTimelineController = studentTimelineController;
document.addEventListener('DOMContentLoaded', () => studentTimelineController.init().catch(() => {}));