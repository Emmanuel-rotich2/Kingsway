/**
 * My Students Performance Controller
 * Teacher view of their assigned students' performance.
 */
const myStudentsPerformanceController = {
  state: { referenceCache: null, students: [], performance: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    this.state.referenceCache = {};
  },

  async load() {
    try {
      const [students, performance] = await Promise.all([
        this.API('GET', '/api/teacher/my-students'),
        this.API('GET', '/api/teacher/my-students-performance'),
      ]);
      this.state.students = Array.isArray(students?.data) ? students.data : (Array.isArray(students) ? students : []);
      this.state.performance = Array.isArray(performance?.data) ? performance.data : (Array.isArray(performance) ? performance : []);
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const students = this.state.students;
    const performance = this.state.performance;
    const body = document.getElementById('mspStudentsBody');
    if (!students.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No assigned students.</td></tr>'; return; }
    body.innerHTML = students.map(s => {
      const perf = performance.find(p => p.student_id === s.id);
      return `<tr>
        <td class="small">${this.esc(s.admission_number || '—')}</td>
        <td class="small">${this.esc(s.name || '—')}</td>
        <td class="small">${this.esc(s.class_name || '—')}</td>
        <td class="small text-center">${perf ? this.esc(perf.average_score || '—') : '—'}</td>
        <td class="small text-center">${perf ? this.esc(perf.rank || '—') : '—'}</td>
        <td><button class="btn btn-sm btn-outline-primary" onclick="myStudentsPerformanceController.viewDetails(${s.id})"><i class="bi bi-eye"></i></button></td>
      </tr>`;
    }).join('');
  },

  async viewDetails(id) { /* open student performance detail */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
    window.APIRealtime?.register?.("my_students_performance", {
      refresh: async () => await this.load()
    }, ["students", "academic"]);
  },
};

window.myStudentsPerformanceController = myStudentsPerformanceController;
document.addEventListener('DOMContentLoaded', () => myStudentsPerformanceController.init().catch(() => {}));