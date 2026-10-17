/**
 * Boarding Students Controller
 * Manages boarding student assignments and dormitory allocations.
 */
const boardingStudentsController = {
  state: { referenceCache: null, students: [], dormitories: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("boarding", "boarding-students-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Boarding Students Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [students, dormitories] = await Promise.all([
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/boarding/students'),
        cache?.dormitories !== undefined ? cache.dormitories : this.API('GET', '/api/boarding/dormitories'),
      ]);
      this.state.students = Array.isArray(students?.data) ? students.data : (Array.isArray(students) ? students : []);
      this.state.dormitories = Array.isArray(dormitories?.data) ? dormitories.data : (Array.isArray(dormitories) ? dormitories : []);
      this.renderStudents();
      this.renderDormitories();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderStudents() {
    const students = this.state.students;
    const body = document.getElementById('bsStudentsBody');
    if (!students.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No boarding students.</td></tr>'; return; }
    body.innerHTML = students.map(s => `<tr>
      <td class="small">${this.esc(s.admission_number || '—')}</td>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.class_name || '—')}</td>
      <td class="small">${this.esc(s.stream_name || '—')}</td>
      <td class="small">${this.esc(s.dormitory || 'Unassigned')}</td>
      <td class="small">${this.esc(s.bed_number || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="boardingStudentsController.assignDormitory(${s.id})"><i class="bi bi-house"></i></button></td>
    </tr>`).join('');
  },

  renderDormitories() {
    const dormitories = this.state.dormitories;
    const container = document.getElementById('bsDormitoriesContainer');
    if (!dormitories.length) { container.innerHTML = '<p class="text-muted">No dormitories configured.</p>'; return; }
    container.innerHTML = dormitories.map(d => `<div class="card mb-2"><div class="card-body">
      <h6>${this.esc(d.name)} (${this.esc(d.capacity)} beds)</h6>
      <small class="text-muted">Occupied: ${d.occupied || 0} | Available: ${d.capacity - (d.occupied || 0)}</small>
    </div></div>`).join('');
  },

  async assignDormitory(studentId) {
    const dormitory = prompt('Enter dormitory ID:');
    const bed = prompt('Enter bed number:');
    if (!dormitory || !bed) return;
    try {
      await this.API('POST', '/api/boarding/assign', { student_id: studentId, dormitory_id: dormitory, bed_number: bed });
      this.notify('Assignment saved', 'success');
      await this.load();
    } catch (e) { this.notify('Assignment failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("boarding_students", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["boarding"]);
  },
};

window.boardingStudentsController = boardingStudentsController;
document.addEventListener('DOMContentLoaded', () => boardingStudentsController.init().catch(() => {}));