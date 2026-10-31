/** Authorized live boarding register. The API enforces dormitory-level scope. */
const boardingRollCallController = {
  state: { dormitories: [], sessions: [], students: [], loading: false },
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); },
  payload(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  value(id) { return document.getElementById(id)?.value || ''; },
  notify(message, type = 'info') { window.showNotification?.(message, type); },
  setVisible(id, visible) { const el = document.getElementById(id); if (el) el.style.display = visible ? '' : 'none'; },
  error(message) {
    const box = document.getElementById('rollCallError');
    if (box) { box.textContent = message; box.classList.remove('d-none'); }
    else this.notify(message, 'danger');
  },
  async init() {
    await window.AuthContext?.ready?.();
    const date = document.getElementById('rollCallDate');
    if (date) { date.value = new Date().toISOString().slice(0, 10); date.max = date.value; }
    document.getElementById('dormitorySelect')?.addEventListener('change', () => this.loadRoster());
    document.getElementById('sessionSelect')?.addEventListener('change', () => this.loadRoster());
    document.getElementById('rollCallDate')?.addEventListener('change', () => this.loadRoster());
    document.getElementById('refreshBtn')?.addEventListener('click', () => this.loadRoster());
    document.getElementById('markAllPresent')?.addEventListener('click', () => this.setAll('present'));
    document.getElementById('markAllAbsent')?.addEventListener('click', () => this.setAll('absent'));
    document.getElementById('submitRollCall')?.addEventListener('click', () => this.submit());
    document.getElementById('studentsTableBody')?.addEventListener('change', (event) => {
      if (event.target.matches('[data-student-status]')) this.renderStats();
    });
    window.APIRealtime?.register?.('boarding_roll_call', { refresh: () => this.loadRoster() }, ['boarding', 'attendance']);
    await this.loadReferences();
  },
  async loadReferences() {
    this.setVisible('loadingState', true); this.setVisible('emptyState', false);
    const error = document.getElementById('rollCallError'); error?.classList.add('d-none');
    try {
      const [dormitoryResponse, sessionResponse] = await Promise.all([
        this.API('GET', '/api/attendance/dormitories'),
        this.API('GET', '/api/attendance/sessions', null, { type: 'boarding' })
      ]);
      this.state.dormitories = this.rows(dormitoryResponse);
      this.state.sessions = this.rows(sessionResponse).filter((row) => !row.type || String(row.type).toLowerCase() === 'boarding');
      const dormitorySelect = document.getElementById('dormitorySelect');
      const sessionSelect = document.getElementById('sessionSelect');
      if (dormitorySelect) dormitorySelect.innerHTML = '<option value="">-- Select Dormitory --</option>' + this.state.dormitories.map((row) => `<option value="${Number(row.id)}">${this.esc(row.name)}</option>`).join('');
      if (sessionSelect) sessionSelect.innerHTML = '<option value="">-- Select Session --</option>' + this.state.sessions.map((row) => `<option value="${Number(row.id)}">${this.esc(row.name)}</option>`).join('');
      this.setVisible('loadingState', false);
      if (!this.state.dormitories.length || !this.state.sessions.length) {
        this.setVisible('emptyState', true);
        const msg = document.querySelector('#emptyState p');
        if (msg) msg.textContent = !this.state.dormitories.length ? 'No dormitory is assigned to your account.' : 'No boarding attendance session is configured.';
        return;
      }
      if (this.state.dormitories.length === 1) dormitorySelect.value = String(this.state.dormitories[0].id);
      if (this.state.sessions.length === 1) sessionSelect.value = String(this.state.sessions[0].id);
      await this.loadRoster();
    } catch (exception) {
      this.setVisible('loadingState', false); this.setVisible('emptyState', true);
      this.error(exception?.message || 'Could not load your authorized boarding register.');
    }
  },
  rows(response) {
    const payload = this.payload(response);
    return Array.isArray(payload) ? payload : (Array.isArray(payload.data) ? payload.data : []);
  },
  async loadRoster() {
    const dormitoryId = this.value('dormitorySelect');
    const sessionId = this.value('sessionSelect');
    const date = this.value('rollCallDate');
    const box = document.getElementById('rollCallError'); box?.classList.add('d-none');
    if (!dormitoryId || !sessionId || !date) {
      this.state.students = []; this.setVisible('rollCallCard', false); this.setVisible('quickStats', false); this.setVisible('summaryCard', false);
      this.setVisible('emptyState', true); return;
    }
    this.setVisible('emptyState', false); this.setVisible('loadingState', true);
    try {
      const response = await this.API('GET', '/api/attendance/dormitory-students', null, { dormitory_id: dormitoryId, session_id: sessionId, date });
      const payload = this.payload(response);
      const roster = payload.students || [];
      this.state.students = Array.isArray(roster) ? roster : [];
      const dormitory = payload.dormitory || this.state.dormitories.find((item) => Number(item.id) === Number(dormitoryId)) || {};
      const session = this.state.sessions.find((item) => Number(item.id) === Number(sessionId)) || {};
      const title = document.getElementById('dormitoryTitle'); if (title) title.textContent = dormitory.name || 'Dormitory';
      const badge = document.getElementById('sessionBadge'); if (badge) badge.textContent = session.name || 'Boarding session';
      const dateLabel = document.getElementById('dateDisplay'); if (dateLabel) dateLabel.textContent = new Date(`${date}T00:00:00`).toLocaleDateString();
      this.renderRoster();
      this.setVisible('loadingState', false); this.setVisible('rollCallCard', this.state.students.length > 0); this.setVisible('quickStats', this.state.students.length > 0);
      this.setVisible('emptyState', this.state.students.length === 0);
      const emptyMessage = document.querySelector('#emptyState p'); if (!this.state.students.length && emptyMessage) emptyMessage.textContent = 'No learners are currently assigned to this dormitory.';
      const submit = document.getElementById('submitRollCall'); if (submit) submit.disabled = date !== new Date().toISOString().slice(0, 10);
    } catch (exception) {
      this.setVisible('loadingState', false); this.setVisible('rollCallCard', false); this.setVisible('emptyState', false);
      this.error(exception?.message || 'Could not load learners for this dormitory.');
    }
  },
  status(student) {
    if (student.permission_id) return 'permission';
    const current = String(student.current_status || '').toLowerCase();
    return ['present','absent','permission','sick_bay','unknown'].includes(current) ? current : 'unknown';
  },
  renderRoster() {
    const body = document.getElementById('studentsTableBody'); if (!body) return;
    body.innerHTML = this.state.students.map((student, index) => {
      const status = this.status(student);
      const label = `${student.first_name || ''} ${student.last_name || ''}`.trim();
      return `<tr class="student-row ${student.permission_id ? 'has-permission' : ''}">
        <td>${index + 1}</td><td>${this.esc(student.admission_no || '—')}</td><td class="fw-semibold">${this.esc(label || student.student_name || '—')}</td>
        <td>${this.esc(student.class_name || '—')}</td><td>${this.esc(student.bed_number || '—')}</td>
        <td>${student.permission_id ? `<span class="badge bg-warning text-dark">${this.esc(student.permission_type || 'Approved leave')}${student.permission_until ? ` until ${this.esc(student.permission_until)}` : ''}</span>` : '—'}</td>
        <td><select class="form-select form-select-sm" data-student-status="${Number(student.id)}" aria-label="Attendance status for ${this.esc(label)}">
          ${[['unknown','Not marked'],['present','Present'],['absent','Absent'],['permission','On permission'],['sick_bay','Sick bay']].map(([value,text]) => `<option value="${value}" ${status === value ? 'selected' : ''}>${text}</option>`).join('')}
        </select></td></tr>`;
    }).join('');
    this.renderStats();
  },
  renderStats() {
    const count = (status) => [...document.querySelectorAll('[data-student-status]')].filter((el) => el.value === status).length;
    const values = { statTotal: this.state.students.length, statPresent: count('present'), statAbsent: count('absent'), statPermission: count('permission'), statSickBay: count('sick_bay') };
    Object.entries(values).forEach(([id, value]) => { const node = document.getElementById(id); if (node) node.textContent = value; });
  },
  setAll(status) {
    document.querySelectorAll('[data-student-status]').forEach((select) => { select.value = status; });
    this.renderStats();
  },
  async submit() {
    const date = this.value('rollCallDate');
    if (date !== new Date().toISOString().slice(0, 10)) return this.error('Boarding attendance can only be submitted for today.');
    const attendance = [...document.querySelectorAll('[data-student-status]')].map((select) => ({ student_id: Number(select.dataset.studentStatus), status: select.value }));
    if (!attendance.length || attendance.some((row) => row.status === 'unknown')) return this.error('Mark every learner before submitting the register.');
    const button = document.getElementById('submitRollCall'); if (button) button.disabled = true;
    try {
      await this.API('POST', '/api/attendance/mark-boarding', {
        dormitory_id: Number(this.value('dormitorySelect')), session_id: Number(this.value('sessionSelect')), date, attendance
      });
      this.notify('Boarding register saved.', 'success');
      await this.loadRoster();
    } catch (exception) { this.error(exception?.message || 'Could not save boarding attendance.'); }
    finally { if (button) button.disabled = date !== new Date().toISOString().slice(0, 10); }
  }
};
window.boardingRollCallController = boardingRollCallController;
document.addEventListener('DOMContentLoaded', () => boardingRollCallController.init());
