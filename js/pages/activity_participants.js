/**
 * activity_participants.js — Activity Participants Controller (standalone page).
 * Register students for activities and manage participation.
 */
const activityParticipantsController = {
  state: { activities: [], students: [], participants: [] },

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

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.activities || r?.participants || r?.students || r?.list || r || []); },

  badgeClass(s) {
    return { active: 'bg-success', pending: 'bg-warning text-dark', withdrawn: 'bg-secondary', inactive: 'bg-secondary' }[s] || 'bg-secondary';
  },

  async load() {
    const body = document.getElementById('apBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const activityId = document.getElementById('apFilterActivity')?.value || '';
      const status = document.getElementById('apFilterStatus')?.value || '';
      const params = {};
      if (activityId) params.activity_id = activityId;
      if (status) params.status = status;

      const [actRes, partRes, stuRes] = await Promise.all([
        this.API('GET', '/activities', null, { limit: 500 }).catch(() => []),
        this.API('GET', '/activities/participants/list', null, params, { silent: true }).catch(() => []),
        this.API('GET', '/students/student', null, { limit: 300 }).catch(() => []),
      ]);

      this.state.activities = this.norm(actRes);
      const partResp = partRes?.data || partRes || [];
      this.state.participants = Array.isArray(partResp) ? partResp : (partResp.participants || []);
      const stuResp = stuRes?.data || stuRes || [];
      this.state.students = Array.isArray(stuResp) ? stuResp : (stuResp.students || []);

      this.renderFilters();
      this.renderStudents();
      this.renderParticipants();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  renderFilters() {
    const sel = document.getElementById('apActivity');
    const filter = document.getElementById('apFilterActivity');
    const opts = this.state.activities.map(a => `<option value="${this.esc(a.id)}">${this.esc(a.title || '')}</option>`).join('');
    sel.innerHTML = opts || '<option value="">No activities available</option>';
    filter.innerHTML = '<option value="">All activities</option>' + opts;
  },

  renderStudents() {
    const sel = document.getElementById('apStudent');
    const students = this.state.students;
    if (!students.length) { sel.innerHTML = '<option value="">No students found</option>'; return; }
    sel.innerHTML = students.map(s => {
      const id = s.id ?? s.student_id;
      const name = s.student_name || `${s.first_name || ''} ${s.last_name || ''}`.trim() || 'Unknown';
      const adm = s.admission_no ? ` (${s.admission_no})` : '';
      return `<option value="${this.esc(id)}">${this.esc(name + adm)}</option>`;
    }).join('');
  },

  async save(event) {
    event.preventDefault();
    const btn = document.getElementById('apBtn');
    const payload = {
      activity_id: parseInt(document.getElementById('apActivity').value, 10) || 0,
      student_id: parseInt(document.getElementById('apStudent').value, 10) || 0,
      role: document.getElementById('apRole').value.trim() || undefined,
    };
    if (!payload.activity_id) { this.notify('Select an activity.', 'danger'); return; }
    if (!payload.student_id) { this.notify('Select a student.', 'danger'); return; }

    btn.disabled = true;
    try {
      const r = await this.API('POST', '/activities/participants/register', payload);
      this.notify(r?.message || 'Participant registered.', 'success');
      event.target.reset();
      await this.load();
    } catch (e) { this.notify(e.message || 'Failed to register participant.', 'danger'); }
    finally { btn.disabled = false; }
  },

  async withdraw(id) {
    if (!(await window.confirmAction('Withdraw participant', 'Withdraw this participant?', { confirmText: 'Withdraw', danger: true }))) return;
    try {
      const r = await this.API('POST', `/activities/participants/withdraw/${id}`, { reason: 'Withdrawn by coordinator' });
      this.notify(r?.message || 'Participant withdrawn.', 'success');
      this.load();
    } catch (e) { this.notify(e.message || 'Failed to withdraw.', 'danger'); }
  },

  renderParticipants() {
    const body = document.getElementById('apBody');
    const list = this.state.participants;
    if (!list.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No participants found.</td></tr>'; return; }
    body.innerHTML = list.map(p => `<tr>
      <td class="small">${this.esc(p.admission_no || '\u2014')}</td>
      <td class="small fw-semibold">${this.esc(`${p.first_name || ''} ${p.last_name || ''}`.trim() || '\u2014')}</td>
      <td class="small">${this.esc(p.class_name || '\u2014')}</td>
      <td class="small">${this.esc(p.activity_title || '\u2014')}</td>
      <td class="small text-muted">${this.esc(p.role || '\u2014')}</td>
      <td><span class="badge ${this.badgeClass(p.status)}">${this.esc(p.status || '\u2014')}</span></td>
      <td class="text-end">${p.status === 'active' ? `<button class="btn btn-sm btn-outline-secondary" data-kw-onclick="activityParticipantsController.withdraw(${p.id})">Withdraw</button>` : ''}</td>
    </tr>`).join('');
  },

  setupEventListeners() {
    document.getElementById('apFilterActivity')?.addEventListener('change', () => this.load());
    document.getElementById('apFilterStatus')?.addEventListener('change', () => this.load());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.activityParticipantsController = activityParticipantsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => activityParticipantsController.init().catch(() => {}));
} else {
  activityParticipantsController.init().catch(() => {});
}
