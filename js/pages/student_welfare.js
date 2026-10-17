/**
 * Student Welfare Controller
 * Manages student welfare cases and interventions.
 */
const studentWelfareController = {
  state: { referenceCache: null, cases: [], students: [], staff: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-welfare-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Welfare Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [cases, students, staff] = await Promise.all([
        cache?.cases !== undefined ? cache.cases : this.API('GET', '/api/students/welfare-cases'),
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/students/get-all', { limit: '500', status: 'active' }),
        cache?.staff !== undefined ? cache.staff : this.API('GET', '/api/staff/staff', { limit: '1000' }),
      ]);
      this.state.cases = Array.isArray(cases?.data) ? cases.data : (Array.isArray(cases) ? cases : []);
      this.state.students = Array.isArray(students?.data) ? students.data : (Array.isArray(students) ? students : []);
      this.state.staff = Array.isArray(staff?.data) ? staff.data : (Array.isArray(staff) ? staff : []);
      this.renderCases();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderCases() {
    const cases = this.state.cases;
    const body = document.getElementById('swCasesBody');
    if (!cases.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No welfare cases.</td></tr>'; return; }
    body.innerHTML = cases.map(c => `<tr>
      <td class="small">${this.esc(c.student_name || '—')}</td>
      <td class="small">${this.esc(c.category || '—')}</td>
      <td class="small"><span class="badge bg-${c.severity === 'high' ? 'danger' : c.severity === 'medium' ? 'warning' : 'info'}">${this.esc(c.severity || '—')}</span></td>
      <td class="small">${this.fmtDate(c.reported_date)}</td>
      <td class="small">${this.esc(c.assigned_to || '—')}</td>
      <td class="small"><span class="badge bg-${c.status === 'open' ? 'warning' : c.status === 'in_progress' ? 'primary' : 'success'}">${this.esc(c.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="studentWelfareController.viewCase(${c.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const students = this.state.students;
    const staff = this.state.staff;
    const studentSelect = document.getElementById('swStudentSelect');
    const assignedSelect = document.getElementById('swAssignedSelect');
    if (studentSelect) studentSelect.innerHTML = '<option value="">Select Student</option>' + students.map(s => `<option value="${s.id}">${this.esc(s.name)} (${this.esc(s.admission_number)})</option>`).join('');
    if (assignedSelect) assignedSelect.innerHTML = '<option value="">Assign To</option>' + staff.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async createCase() {
    const student = document.getElementById('swStudentSelect')?.value;
    const category = document.getElementById('swCategory')?.value;
    const severity = document.getElementById('swSeverity')?.value;
    const description = document.getElementById('swDescription')?.value;
    const assigned = document.getElementById('swAssignedSelect')?.value;
    if (!student || !category || !description) { this.notify('Required fields missing', 'warning'); return; }
    try {
      await this.API('POST', '/api/students/welfare-case', { student_id: student, category, severity, description, assigned_to: assigned });
      this.notify('Case created', 'success');
      document.getElementById('swCaseForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Creation failed', 'danger'); }
  },

  async viewCase(id) { /* open case detail modal */ },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("student_welfare", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["students", "counseling"]);
  },
};

window.studentWelfareController = studentWelfareController;
document.addEventListener('DOMContentLoaded', () => studentWelfareController.init().catch(() => {}));