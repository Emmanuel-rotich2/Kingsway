/**
 * Student Leadership Controller
 * Manages student leadership roles, houses, and awards.
 */
const studentLeadershipController = {
  state: { referenceCache: null, leadership: [], houses: [], positions: [], awards: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-leadership-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Leadership Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [leadership, houses, positions, awards] = await Promise.all([
        cache?.leadership !== undefined ? cache.leadership : this.API('GET', '/api/students/leadership'),
        cache?.houses !== undefined ? cache.houses : this.API('GET', '/api/students/houses-list'),
        cache?.positions !== undefined ? cache.positions : this.API('GET', '/api/students/leadership-positions'),
        cache?.awards !== undefined ? cache.awards : this.API('GET', '/api/students/awards-list'),
      ]);
      this.state.leadership = Array.isArray(leadership?.data) ? leadership.data : (Array.isArray(leadership) ? leadership : []);
      this.state.houses = Array.isArray(houses?.data) ? houses.data : (Array.isArray(houses) ? houses : []);
      this.state.positions = Array.isArray(positions?.data) ? positions.data : (Array.isArray(positions) ? positions : []);
      this.state.awards = Array.isArray(awards?.data) ? awards.data : (Array.isArray(awards) ? awards : []);
      this.renderLeadership();
      this.renderHouses();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderLeadership() {
    const leadership = this.state.leadership;
    const body = document.getElementById('slLeadershipBody');
    if (!leadership.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No leadership roles assigned.</td></tr>'; return; }
    body.innerHTML = leadership.map(l => `<tr>
      <td class="small">${this.esc(l.student_name || '—')}</td>
      <td class="small">${this.esc(l.house || '—')}</td>
      <td class="small">${this.esc(l.position || '—')}</td>
      <td class="small">${this.fmtDate(l.start_date)}</td>
      <td class="small">${this.fmtDate(l.end_date) || 'Current'}</td>
      <td><button class="btn btn-sm btn-outline-danger" onclick="studentLeadershipController.removeRole(${l.id})"><i class="bi bi-trash"></i></button></td>
    </tr>`).join('');
  },

  renderHouses() {
    const houses = this.state.houses;
    const container = document.getElementById('slHousesContainer');
    if (!houses.length) { container.innerHTML = '<p class="text-muted">No houses defined.</p>'; return; }
    container.innerHTML = houses.map(h => `<div class="card mb-2"><div class="card-body"><h6>${this.esc(h.name)}</h6><small class="text-muted">${this.esc(h.description)}</small></div></div>`).join('');
  },

  renderFormOptions() {
    const students = this.getCache('students') || [];
    const positions = this.state.positions;
    const studentSelect = document.getElementById('slStudentSelect');
    const positionSelect = document.getElementById('slPositionSelect');
    if (studentSelect) studentSelect.innerHTML = '<option value="">Select Student</option>' + students.map(s => `<option value="${s.id}">${this.esc(s.name)} (${this.esc(s.admission_number)})</option>`).join('');
    if (positionSelect) positionSelect.innerHTML = '<option value="">Select Position</option>' + positions.map(p => `<option value="${p.id}">${this.esc(p.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async assignRole() {
    const student = document.getElementById('slStudentSelect')?.value;
    const position = document.getElementById('slPositionSelect')?.value;
    const house = document.getElementById('slHouseSelect')?.value;
    if (!student || !position) { this.notify('Student and position required', 'warning'); return; }
    try {
      await this.API('POST', '/api/students/leadership', { student_id: student, position_id: position, house_id: house });
      this.notify('Role assigned', 'success');
      document.getElementById('slAssignForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Assignment failed', 'danger'); }
  },

  async removeRole(id) {
    if (!confirm('Remove this leadership role?')) return;
    try {
      await this.API('DELETE', `/api/students/leadership/${id}`);
      this.notify('Role removed', 'success');
      await this.load();
    } catch (e) { this.notify('Removal failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("student_leadership", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["students"]);
  },
};

window.studentLeadershipController = studentLeadershipController;
document.addEventListener('DOMContentLoaded', () => studentLeadershipController.init().catch(() => {}));