/**
 * student_route_assignment.js — Student Route Assignment Controller (standalone page).
 * Assign/withdraw students to transport routes and stops.
 */
const routeAssignController = {
  state: { routes: [], stops: [], assignments: [], referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  fillMonths() {
    const now = new Date();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    ['assignMonth'].forEach(id => {
      const sel = document.getElementById(id);
      sel.innerHTML = months.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
      sel.value = String(now.getMonth() + 1);
    });
    ['assignYear'].forEach(id => {
      const sel = document.getElementById(id);
      sel.innerHTML = '';
      for (let y = now.getFullYear(); y >= now.getFullYear() - 1; y--) {
        sel.innerHTML += `<option value="${y}">${y}</option>`;
      }
      sel.value = String(now.getFullYear());
    });
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-route-assignment-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Route Assignment Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async loadRoutes() {
    try {
      const cache = this.state.referenceCache;
      const r = cache?.routes !== undefined ? cache.routes : await this.API('GET', 'transport/all-routes');
      this.state.routes = r?.data || r?.items || [];
      const opts = this.state.routes.map(x => `<option value="${x.id}">${this.esc(x.name || x.route_name || x.id)}</option>`).join('');
      document.getElementById('assignRoute').innerHTML = opts;
      document.getElementById('viewRoute').innerHTML = opts;
    } catch (_) {}
  },

  async loadStops() {
    try {
      const cache = this.state.referenceCache;
      const r = cache?.stops !== undefined ? cache.stops : await this.API('GET', 'transport/all-stops');
      this.state.stops = r?.data || r?.items || [];
      document.getElementById('assignStop').innerHTML = '<option value="">— Select stop —</option>' +
        this.state.stops.map(s => `<option value="${s.id}">${this.esc(s.name || s.id)}</option>`).join('');
    } catch (_) {}
  },

  async loadRouteStudents() {
    const body = document.getElementById('routeStudentsBody');
    const routeId = document.getElementById('viewRoute').value;
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    if (!routeId) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Select a route to view its students.</td></tr>'; return; }
    try {
      const r = await this.API('GET', 'transport/students-by-route', null, { route_id: routeId });
      this.state.assignments = r?.data || [];
      this.renderAssignments();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  renderAssignments() {
    const body = document.getElementById('routeStudentsBody');
    if (!this.state.assignments.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No students assigned to this route.</td></tr>'; return; }
    body.innerHTML = this.state.assignments.map(a => `
      <tr>
        <td class="small"><span class="badge bg-light text-dark border">${this.esc(a.admission_no || '\u2014')}</span></td>
        <td class="fw-semibold small">${this.esc(`${a.first_name || ''} ${a.last_name || ''}`.trim() || '\u2014')}</td>
        <td class="small text-muted">${this.esc(a.stop_name || '\u2014')}</td>
        <td class="small">${this.esc(a.assigned_at || '—')}</td>
        <td><button class="btn btn-sm btn-outline-danger" onclick="routeAssignController.unassign(${a.student_id}, ${a.route_id})"><i class="bi bi-x-lg"></i></button></td>
      </tr>
    `).join('');
  },

  async assignStudent() {
    const studentId = document.getElementById('assignStudent').value;
    const routeId = document.getElementById('assignRoute').value;
    const stopId = document.getElementById('assignStop').value;
    if (!studentId || !routeId || !stopId) return this.notify('Select student, route, and stop', 'warning');

    try {
      await this.API('POST', 'transport/assign-student', { student_id: studentId, route_id: routeId, stop_id: stopId });
      this.notify('Student assigned to route', 'success');
      await this.loadRouteStudents();
    } catch (e) { this.notify(e.message || 'Assignment failed', 'danger'); }
  },

  async unassign(studentId, routeId) {
    if (!confirm('Remove this student from the route?')) return;
    try {
      await this.API('POST', 'transport/unassign-student', { student_id: studentId, route_id: routeId });
      this.notify('Student unassigned', 'success');
      await this.loadRouteStudents();
    } catch (e) { this.notify(e.message || 'Unassignment failed', 'danger'); }
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-route-assignment-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Route Assignment Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
    await this.loadReferencesComposed();
    await Promise.all([this.loadRoutes(), this.loadStops()]);
    document.getElementById('assignForm')?.addEventListener('submit', e => { e.preventDefault(); this.assignStudent(); });
    document.getElementById('viewRoute')?.addEventListener('change', () => this.loadRouteStudents());
    window.APIRealtime?.register?.("student_route_assignment", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await Promise.all([this.loadRoutes(), this.loadStops()]);
      }
    }, ["transport", "students"]);
  },
};

window.routeAssignController = routeAssignController;

document.addEventListener('DOMContentLoaded', () => routeAssignController.init().catch(() => {}));