/**
 * student_route_assignment.js — Student Route Assignment Controller (standalone page).
 * Assign/withdraw students to transport routes and stops.
 *
 * The markup (pages/student_route_assignment.php) identifies the learner by
 * admission number or parent phone (#assignQuery), verified through
 * POST /transport/verify-student. The roster loads on open for the first route
 * and reloads whenever the route, month or year changes (no Load button).
 */
const routeAssignController = {
  state: { routes: [], stops: [], assignments: [], referenceCache: null, student: null, verifyTimer: null, loadSeq: 0 },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  el(id) { return document.getElementById(id); },

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },

  /** Accepts a bare array, {data: [...]}, {items: [...]} or {data: {data: [...]}}. */
  rows(r) {
    if (Array.isArray(r)) return r;
    if (Array.isArray(r?.data)) return r.data;
    if (Array.isArray(r?.items)) return r.items;
    if (Array.isArray(r?.data?.data)) return r.data.data;
    return [];
  },

  monthName(m) {
    const n = Number(m);
    return n >= 1 && n <= 12 ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][n - 1] : '';
  },

  fillMonths() {
    const now = new Date();
    const month = this.el('assignMonth');
    const year = this.el('assignYear');
    if (month) {
      month.innerHTML = Array.from({ length: 12 }, (_, i) => `<option value="${i + 1}">${this.monthName(i + 1)}</option>`).join('');
      month.value = String(now.getMonth() + 1);
    }
    if (year) {
      const years = [now.getFullYear() + 1, now.getFullYear(), now.getFullYear() - 1];
      year.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
      year.value = String(now.getFullYear());
    }
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read('students', 'student-route-assignment-references');
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn('Student Route Assignment Node view failed; using direct reads:', error);
      this.state.referenceCache = null;
    }
  },

  async loadRoutes() {
    const cache = this.state.referenceCache;
    let r = cache?.routes;
    if (r === undefined || this.rows(r).length === 0) {
      try { r = await this.API('GET', 'transport/all-routes'); } catch (_) { r = []; }
    }
    this.state.routes = this.rows(r).filter(x => !x.status || x.status === 'active');
    const opts = this.state.routes.map(x => `<option value="${x.id}">${this.esc(x.name || x.route_name || x.code || x.id)}</option>`).join('');
    const empty = '<option value="">No active routes</option>';
    this.el('assignRoute').innerHTML = opts || empty;
    this.el('viewRoute').innerHTML = opts || empty;
  },

  async loadStops() {
    const cache = this.state.referenceCache;
    let r = cache?.stops;
    if (r === undefined || this.rows(r).length === 0) {
      try { r = await this.API('GET', 'transport/all-stops'); } catch (_) { r = []; }
    }
    this.state.stops = this.rows(r).filter(s => !s.status || s.status === 'active');
    this.renderStops();
  },

  /** Stops belong to a route: only offer the selected route's stops, in order. */
  renderStops() {
    const routeId = String(this.el('assignRoute')?.value || '');
    const stops = this.state.stops
      .filter(s => !routeId || String(s.route_id) === routeId)
      .sort((a, b) => (Number(a.sequence) || 0) - (Number(b.sequence) || 0));
    this.el('assignStop').innerHTML = '<option value="">— No stop / not yet chosen —</option>' +
      stops.map(s => `<option value="${s.id}">${this.esc(s.name || s.id)}</option>`).join('');
  },

  /** Admission number or parent phone, verified as the user types. */
  scheduleVerify() {
    clearTimeout(this.state.verifyTimer);
    this.state.student = null;
    const out = this.el('verifiedStudent');
    const q = (this.el('assignQuery')?.value || '').trim();
    if (!q) { out.textContent = ''; return; }
    out.className = 'small text-muted mb-2';
    out.textContent = 'Checking…';
    this.state.verifyTimer = setTimeout(() => this.verifyStudent(q), 450);
  },

  async verifyStudent(q) {
    const out = this.el('verifiedStudent');
    const isPhone = /^\+?[\d\s-]{9,}$/.test(q) && !/[a-z]/i.test(q);
    try {
      const r = await this.API('POST', 'transport/verify-student', isPhone ? { phone: q.replace(/[\s-]/g, '') } : { admission_no: q });
      // Ignore a late answer for text the user has since changed.
      if ((this.el('assignQuery')?.value || '').trim() !== q) return;
      const s = r?.data && !Array.isArray(r.data) ? r.data : r;
      if (s && s.id) {
        this.state.student = s;
        out.className = 'small text-success fw-semibold mb-2';
        out.textContent = `✓ ${[s.first_name, s.last_name].filter(Boolean).join(' ')}${s.admission_no ? ' · ' + s.admission_no : ''}`;
      } else {
        out.className = 'small text-danger mb-2';
        out.textContent = 'No learner found for that admission number or phone.';
      }
    } catch (_) {
      if ((this.el('assignQuery')?.value || '').trim() !== q) return;
      out.className = 'small text-danger mb-2';
      out.textContent = 'No learner found for that admission number or phone.';
    }
  },

  async loadRouteStudents() {
    const body = this.el('routeStudentsBody');
    const routeId = this.el('viewRoute')?.value || '';
    if (!routeId) {
      body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No active routes yet. Add one under Manage Transport.</td></tr>';
      return;
    }
    const seq = ++this.state.loadSeq;
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'transport/students-by-route', null, {
        route_id: routeId,
        month: this.el('assignMonth')?.value || '',
        year: this.el('assignYear')?.value || '',
      });
      if (seq !== this.state.loadSeq) return; // a newer filter change won
      this.state.assignments = this.rows(r);
      this.renderAssignments();
    } catch (e) {
      if (seq !== this.state.loadSeq) return;
      body.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">Could not load the students on this route. Please try again.</td></tr>';
    }
  },

  renderAssignments() {
    const body = this.el('routeStudentsBody');
    if (!this.state.assignments.length) {
      const m = this.monthName(this.el('assignMonth')?.value);
      const y = this.el('assignYear')?.value || '';
      body.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No students assigned to this route for ${this.esc(`${m} ${y}`.trim())}.</td></tr>`;
      return;
    }
    body.innerHTML = this.state.assignments.map(a => {
      const period = [this.monthName(a.month), a.year].filter(Boolean).join(' ') || '—';
      const status = a.status || 'active';
      return `
      <tr>
        <td class="small"><span class="badge bg-light text-dark border">${this.esc(a.admission_no || '—')}</span></td>
        <td class="fw-semibold small">${this.esc(`${a.first_name || ''} ${a.last_name || ''}`.trim() || '—')}</td>
        <td class="small text-muted">${this.esc(a.stop_name || '—')}</td>
        <td class="small">${this.esc(period)}</td>
        <td class="small"><span class="badge bg-${status === 'active' ? 'success' : 'secondary'}">${this.esc(status)}</span></td>
        <td class="text-end"><button class="btn btn-sm btn-outline-danger" title="Remove from route" aria-label="Remove from route" data-kw-onclick="routeAssignController.unassign(${Number(a.id)})"><i class="bi bi-x-lg"></i></button></td>
      </tr>`;
    }).join('');
  },

  /** Called by the Assign Student button (data-kw-onclick="routeAssignController.assign()"). */
  async assign() {
    const routeId = this.el('assignRoute')?.value || '';
    const stopId = this.el('assignStop')?.value || '';
    const q = (this.el('assignQuery')?.value || '').trim();
    if (!routeId) return this.notify('Choose a route first.', 'warning');
    if (!q) return this.notify('Enter the admission number or parent phone.', 'warning');
    if (!this.state.student?.id) {
      clearTimeout(this.state.verifyTimer);
      await this.verifyStudent(q);
      if (!this.state.student?.id) return this.notify('Check the admission number or phone: no learner matched.', 'warning');
    }
    const btn = this.el('assignBtn');
    if (btn) btn.disabled = true;
    try {
      await this.API('POST', 'transport/assign-student', {
        student_id: this.state.student.id,
        route_id: routeId,
        stop_id: stopId || null,
        month: this.el('assignMonth')?.value || '',
        year: this.el('assignYear')?.value || '',
      });
      this.notify('Student assigned to route', 'success');
      this.el('assignQuery').value = '';
      this.el('verifiedStudent').textContent = '';
      this.state.student = null;
      // Show the roster the learner was just added to.
      if (this.el('viewRoute').value !== routeId) this.el('viewRoute').value = routeId;
      await this.loadRouteStudents();
    } catch (e) {
      this.notify(e.message || 'Assignment failed', 'danger');
    } finally {
      if (btn) btn.disabled = false;
    }
  },

  async unassign(assignmentId) {
    if (!(await window.confirmAction('Remove from route', 'Remove this student from the route?', { confirmText: 'Remove', danger: true }))) return;
    try {
      await this.API('POST', 'transport/unassign-student', { assignment_id: assignmentId });
      this.notify('Student unassigned', 'success');
      await this.loadRouteStudents();
    } catch (e) { this.notify(e.message || 'Unassignment failed', 'danger'); }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
    this.fillMonths();
    await this.loadReferencesComposed();
    await this.loadRoutes();
    await this.loadStops();

    this.el('assignRoute')?.addEventListener('change', () => this.renderStops());
    this.el('assignQuery')?.addEventListener('input', () => this.scheduleVerify());
    this.el('assignQuery')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); this.assign(); } });
    ['viewRoute', 'assignMonth', 'assignYear'].forEach(id => this.el(id)?.addEventListener('change', () => this.loadRouteStudents()));

    // Filter-driven view: show the first route's roster straight away.
    await this.loadRouteStudents();

    window.APIRealtime?.register?.('student_route_assignment', {
      refresh: async () => {
        const view = this.el('viewRoute')?.value;
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.loadRoutes();
        await this.loadStops();
        if (view && this.state.routes.some(r => String(r.id) === String(view))) this.el('viewRoute').value = view;
        await this.loadRouteStudents();
      }
    }, ['transport', 'students']);
  },
};

window.routeAssignController = routeAssignController;

function initializeStudentRouteAssignmentPage() {
  routeAssignController.init().catch((error) => console.error('Student route assignment failed to initialize:', error));
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeStudentRouteAssignmentPage, { once: true });
} else {
  initializeStudentRouteAssignmentPage();
}
