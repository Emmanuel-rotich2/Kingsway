/**
 * Student Promotion Controller
 * Handles student promotion workflows.
 */
const studentPromotionController = {
  state: { referenceCache: null, students: [], promotions: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-promotion-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Promotion Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [students, promotions] = await Promise.all([
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/students/promotion-eligible'),
        cache?.promotions !== undefined ? cache.promotions : this.API('GET', '/api/students/promotions'),
      ]);
      this.state.students = Array.isArray(students?.data) ? students.data : (Array.isArray(students) ? students : []);
      this.state.promotions = Array.isArray(promotions?.data) ? promotions.data : (Array.isArray(promotions) ? promotions : []);
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const students = this.state.students;
    const body = document.getElementById('spStudentsBody');
    if (!students.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No eligible students.</td></tr>'; return; }
    body.innerHTML = students.map(s => `<tr>
      <td class="small">${this.esc(s.admission_number || '—')}</td>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.current_class || '—')}</td>
      <td class="small">${this.esc(s.current_stream || '—')}</td>
      <td class="small text-center">
        <select class="form-select form-select-sm" data-student-id="${s.id}" onchange="studentPromotionController.updateTargetClass(this)">
          <option value="">Select Class</option>
          ${(this.getCache('classes') || []).map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('')}
        </select>
      </td>
      <td><button class="btn btn-sm btn-primary" onclick="studentPromotionController.promoteStudent(${s.id})">Promote</button></td>
    </tr>`).join('');
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  updateTargetClass(select) { /* handle target class selection */ },

  async promoteStudent(studentId) {
    try {
      await this.API('POST', '/api/students/promote', { student_id: studentId });
      this.notify('Student promoted successfully', 'success');
      await this.load();
    } catch (e) { this.notify('Promotion failed', 'danger'); }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("student_promotion", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["students", "academic"]);
  },
};

window.studentPromotionController = studentPromotionController;
document.addEventListener('DOMContentLoaded', () => studentPromotionController.init().catch(() => {}));