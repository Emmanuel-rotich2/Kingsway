/**
 * chaplaincy_pastoral.js — Spiritual Groups & Pastoral Care Controller (Phase C).
 * Confidential: learner spiritual profiles, milestones and pastoral visits.
 */
const ChaplaincyPastoralController = {
  state: {
    groups: [],
    groupStates: {},        // groupId -> { members, attDate, attRows }
    studentResults: [],
    currentProfile: null,   // { studentId, data }
  },

  API(path, method = 'GET', data = null, params = null) {
    return window.callAPI('/chaplaincy' + path, method, data, params);
  },

  esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },

  fmtDate(v) {
    if (!v) return '\u2014';
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? this.esc(v) : d.toLocaleDateString();
  },

  badge(label, cls = 'bg-secondary') {
    return `<span class="badge ${cls}">${this.esc(label)}</span>`;
  },

  typeLabel(t) {
    return String(t || '').replace(/_/g, ' ');
  },

  setActiveTab(name) {
    document.querySelectorAll('.cpast-tab').forEach((b) =>
      b.classList.toggle('active', b.getAttribute('data-cpast-tab') === name));
    document.querySelectorAll('.cpast-pane').forEach((p) =>
      p.classList.toggle('d-none', p.id !== 'cpastPane-' + name));
  },

  /* ================= Groups ================= */
  async loadGroups() {
    const res = await this.API('/groups').catch(() => null);
    this.state.groups = res?.data || res || [];
    this.renderGroups();
  },

  renderGroups() {
    const wrap = document.getElementById('cpastGroups');
    if (!wrap) return;
    if (!this.state.groups.length) {
      wrap.innerHTML = '<div class="col-12 text-center text-muted py-4">No spiritual groups yet.</div>';
      return;
    }
    wrap.innerHTML = this.state.groups.map((g) => `
      <div class="col-md-6 col-xl-4">
        <div class="card h-100 border">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <span class="badge ${g.is_active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'} fw-normal">${this.esc(this.typeLabel(g.group_type))}</span>
              <span class="text-muted small">${this.esc(g.member_count ?? 0)} members</span>
            </div>
            <h6 class="mt-2 mb-1">${this.esc(g.name)}</h6>
            <div class="text-muted small mb-2">
              ${g.meeting_day ? this.esc(g.meeting_day[0] + g.meeting_day.slice(1).toLowerCase()) : 'Flexible'}
              ${g.meeting_time ? ' ' + (g.meeting_time || '').slice(0, 5) : ''}${g.meeting_location ? ' &middot; ' + this.esc(g.meeting_location) : ''}
            </div>
            <div class="text-muted small">${g.leader_name ? 'Leader: ' + this.esc(g.leader_name) : ''}</div>
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-between">
            <span class="small text-muted">${this.esc(g.description || '')}</span>
            <button class="btn btn-sm btn-outline-primary ms-2" data-cpast-role="group-detail" data-id="${this.esc(g.id)}">Manage</button>
          </div>
        </div>
      </div>`).join('');
  },

  async openGroupDetail(id) {
    const members = (await this.API(`/groups/${id}/members`).catch(() => null));
    this.state.groupStates[id] = {
      members: members?.data || members || [],
      attDate: new Date().toISOString().slice(0, 10),
      attRows: [],
    };
    const g = this.state.groups.find((x) => String(x.id) === String(id));
    document.getElementById('cpastGrpTitle').textContent = g ? g.name : ('Group #' + id);
    this.renderGroupMembers(id);
    await this.loadGroupAttendance(id);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('cpastGroupDetailModal')).show();
  },

  renderGroupMembers(id) {
    const st = this.state.groupStates[id];
    const tb = document.getElementById('cpastGrpMembers');
    if (!st || !tb) return;
    if (!st.members.length) { tb.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No members.</td></tr>'; return; }
    tb.innerHTML = st.members.map((m) => `
      <tr>
        <td class="small">${this.esc(m.name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(m.member_type)}</td>
        <td class="small">${this.badge(m.status, m.status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border')}</td>
        <td class="text-end"><button class="btn btn-sm btn-outline-danger" data-cpast-role="member-remove" data-gid="${this.esc(id)}" data-mid="${this.esc(m.id)}"><i class="bi bi-x"></i></button></td>
      </tr>`).join('');
  },

  async addGroupMember(id) {
    const sid = document.getElementById('cpastAddMemberInput').value.trim();
    const type = document.getElementById('cpastAddMemberType').value;
    if (!/^\d+$/.test(sid)) { showNotification?.('Enter a numeric student/staff ID', 'error'); return; }
    const res = await this.API(`/groups/${id}/members`, 'POST', {
      member_type: type, student_id: type === 'student' ? +sid : null, staff_id: type === 'staff' ? +sid : null,
    });
    if (res?.data || res?.success || res?.status === 'success') {
      showNotification?.('Member added', 'success');
      document.getElementById('cpastAddMemberInput').value = '';
      this.openGroupDetail(id);
    } else {
      showNotification?.(res?.message || 'Failed to add member', 'error');
    }
  },

  async removeGroupMember(gid, mid) {
    if (!confirm('Remove this member?')) return;
    const res = await this.API(`/group-members/${mid}`, 'DELETE');
    if (res?.data || res?.status === 'success') {
      showNotification?.('Member removed', 'success');
      this.openGroupDetail(gid);
    }
  },

  async loadGroupAttendance(id) {
    const st = this.state.groupStates[id];
    if (!st) return;
    document.getElementById('cpastAttDate').value = st.attDate;
    const res = await this.API(`/groups/${id}/attendance`, 'GET', null, { meeting_date: st.attDate }).catch(() => null);
    const att = res?.data?.attendance || [];
    if (att.length) {
      st.attRows = att.map((a) => ({ member_type: a.member_type, student_id: a.student_id, staff_id: a.staff_id, name: a.name, attended: !!a.attended }));
    } else {
      st.attRows = st.members.map((m) => ({ member_type: m.member_type, student_id: m.student_id, staff_id: m.staff_id, name: m.name, attended: false }));
    }
    this.renderGroupAttendance(id);
  },

  renderGroupAttendance(id) {
    const st = this.state.groupStates[id];
    const tb = document.getElementById('cpastGroupAtt');
    if (!st || !tb) return;
    if (!st.attRows.length) { tb.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No members to mark.</td></tr>'; return; }
    tb.innerHTML = st.attRows.map((r, i) => `
      <tr>
        <td><input type="checkbox" class="form-check-input" data-cpast-att data-i="${i}" ${r.attended ? 'checked' : ''}></td>
        <td class="small">${this.esc(r.name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(r.member_type)}</td>
      </tr>`).join('');
  },

  async saveGroupAttendance(id) {
    const st = this.state.groupStates[id];
    if (!st) return;
    const rows = st.attRows.map((r, i) => {
      const cb = document.querySelector(`[data-cpast-att][data-i="${i}"]`);
      return { member_type: r.member_type, student_id: r.student_id, staff_id: r.staff_id, attended: cb ? cb.checked : r.attended };
    });
    const res = await this.API(`/groups/${id}/attendance`, 'PUT', { meeting_date: st.attDate, attendance: rows });
    if (res?.data || res?.status === 'success') {
      showNotification?.(`Attendance saved (${res.data?.saved ?? rows.length})`, 'success');
    } else {
      showNotification?.(res?.message || 'Failed to save attendance', 'error');
    }
  },

  async saveGroup() {
    const name = document.getElementById('cpastGrpName').value.trim();
    if (!name) { showNotification?.('Group name is required', 'error'); return; }
    const leader = document.getElementById('cpastGrpLeader').value.trim();
    const res = await this.API('/groups', 'POST', {
      code: name.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase().slice(0, 40),
      name,
      group_type: document.getElementById('cpastGrpType').value,
      meeting_day: document.getElementById('cpastGrpDay').value || null,
      meeting_time: document.getElementById('cpastGrpTime').value || null,
      meeting_location: document.getElementById('cpastGrpLoc').value || null,
      leader_id: /^\d+$/.test(leader) ? +leader : null,
      description: document.getElementById('cpastGrpDesc').value || null,
    });
    if (res?.data?.id || res?.status === 'success') {
      showNotification?.('Group created', 'success');
      bootstrap.Modal.getInstance(document.getElementById('cpastGroupModal'))?.hide();
      this.loadGroups();
    } else {
      showNotification?.(res?.message || 'Failed to create group', 'error');
    }
  },

  /* ================= Spiritual Profiles ================= */
  async searchStudents() {
    const q = document.getElementById('cpastStudentSearch').value.trim();
    const params = {};
    if (q) {
      if (/^\d+$/.test(q)) params.id = q;
      else params.search = q;
    }
    const res = await window.callAPI('/students/student', 'GET', null, params).catch(() => null);
    const payload = res?.data?.data ?? res?.data ?? res;
    this.state.studentResults = payload?.students ?? (Array.isArray(payload) ? payload : []);
    this.renderStudentResults();
  },

  renderStudentResults() {
    const tb = document.getElementById('cpastStudentResults');
    if (!tb) return;
    if (!this.state.studentResults.length) {
      tb.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No learners found.</td></tr>';
      return;
    }
    tb.innerHTML = this.state.studentResults.map((s) => `
      <tr data-cpast-role="profile-open" data-id="${this.esc(s.id)}" style="cursor:pointer">
        <td class="small">${this.esc(s.name || s.first_name || ('Student #' + s.id))}</td>
        <td class="small text-muted">${this.esc(s.admission_no || '\u2014')}</td>
      </tr>`).join('');
  },

  async openProfile(studentId) {
    const res = await this.API(`/spiritual-profiles/${studentId}`).catch(() => null);
    if (!res?.data) { showNotification?.(res?.message || 'Could not load profile', 'error'); return; }
    this.state.currentProfile = { studentId, data: res.data };
    this.renderProfile();
  },

  renderProfile() {
    const v = document.getElementById('cpastProfileView');
    if (!v || !this.state.currentProfile) return;
    const p = this.state.currentProfile.data;
    const esc = (x) => this.esc(x ?? '');
    v.innerHTML = `
      <div class="border rounded-3 p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0"><i class="bi bi-person-badge me-1 text-primary"></i>${esc(p.student_name)}</h6>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Confidential</span>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small text-muted">Baptism Status</label>
            <select class="form-select form-select-sm cpast-pfield" data-field="baptism_status">
              ${['not_baptized', 'preparing', 'baptized', 'other'].map((x) => `<option value="${x}" ${p.baptism_status === x ? 'selected' : ''}>${this.typeLabel(x)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Baptism Date</label>
            <input type="date" class="form-control form-control-sm cpast-pfield" data-field="baptism_date" value="${esc(p.baptism_date)}">
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Baptism Church</label>
            <input type="text" class="form-control form-control-sm cpast-pfield" data-field="baptism_church" value="${esc(p.baptism_church)}">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Church Membership</label>
            <input type="text" class="form-control form-control-sm cpast-pfield" data-field="church_membership" value="${esc(p.church_membership)}">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Sabbath School Class</label>
            <input type="text" class="form-control form-control-sm cpast-pfield" data-field="sabbath_school_class" value="${esc(p.sabbath_school_class)}">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Spiritual Gifts</label>
            <input type="text" class="form-control form-control-sm cpast-pfield" data-field="spiritual_gifts" value="${esc(p.spiritual_gifts)}">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Interests</label>
            <input type="text" class="form-control form-control-sm cpast-pfield" data-field="interests" value="${esc(p.interests)}">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Prayer Concerns</label>
            <textarea class="form-control form-control-sm cpast-pfield" data-field="prayer_concerns" rows="2">${esc(p.prayer_concerns)}</textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Pastoral Notes</label>
            <textarea class="form-control form-control-sm cpast-pfield" data-field="pastoral_notes" rows="2">${esc(p.pastoral_notes)}</textarea>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-sm btn-primary" data-cpast-role="profile-save">Save Profile</button>
          <button class="btn btn-sm btn-outline-primary" data-cpast-role="milestone-add"><i class="bi bi-plus-lg"></i> Add Milestone</button>
        </div>
        <hr>
        <h6 class="small text-muted">Spiritual Milestones</h6>
        <div id="cpastMilestones">${(p.milestones || []).map((m) => `
          <div class="d-flex justify-content-between border-bottom py-2">
            <span class="small">${this.fmtDate(m.milestone_date)} &middot; <strong>${this.esc(m.title)}</strong>${m.details ? ' — ' + this.esc(m.details) : ''}</span>
            <span class="badge bg-light text-muted border">${this.esc(this.typeLabel(m.milestone_type))}</span>
          </div>`).join('') || '<div class="text-muted small py-2">No milestones recorded.</div>'}
        </div>
      </div>`;
  },

  async saveProfile() {
    const sid = this.state.currentProfile?.studentId;
    if (!sid) return;
    const data = {};
    document.querySelectorAll('.cpast-pfield').forEach((el) => {
      const f = el.getAttribute('data-field');
      const v = el.value.trim();
      data[f] = v === '' ? null : v;
    });
    const res = await this.API(`/spiritual-profiles/${sid}`, 'PUT', data);
    if (res?.data || res?.status === 'success') {
      showNotification?.('Profile saved', 'success');
    } else {
      showNotification?.(res?.message || 'Failed to save profile', 'error');
    }
  },

  async addMilestone() {
    const sid = this.state.currentProfile?.studentId;
    if (!sid) return;
    const type = prompt('Milestone type (baptism / commitment / investiture / award / dedication / other):', 'baptism');
    if (!type) return;
    const date = prompt('Milestone date (YYYY-MM-DD):');
    const title = prompt('Title:');
    if (!date || !title) { showNotification?.('Date and title are required', 'error'); return; }
    const res = await this.API(`/spiritual-profiles/${sid}/milestones`, 'POST', { milestone_type: type, milestone_date: date, title });
    if (res?.data?.id || res?.status === 'success') {
      showNotification?.('Milestone added', 'success');
      this.openProfile(sid);
    } else {
      showNotification?.(res?.message || 'Failed to add milestone', 'error');
    }
  },

  /* ================= Pastoral Visits ================= */
  async loadVisits() {
    const res = await this.API('/pastoral-visits').catch(() => null);
    const visits = res?.data || res || [];
    const tb = document.getElementById('cpastVisits');
    if (!tb) return;
    if (!visits.length) { tb.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No pastoral visits recorded.</td></tr>'; return; }
    tb.innerHTML = visits.map((v) => `
      <tr>
        <td class="small text-nowrap">${this.fmtDate(v.visit_date)}</td>
        <td class="small">${this.esc(v.subject_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(this.typeLabel(v.visit_type))}</td>
        <td class="small text-muted">${this.esc(v.purpose || '\u2014')}</td>
        <td class="small text-nowrap">${this.fmtDate(v.next_action_at)}</td>
        <td class="small">${this.badge(v.status, v.status === 'closed' ? 'bg-secondary-subtle text-secondary border' : v.status === 'followup' ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle')}</td>
        <td class="small text-muted">${this.esc(v.conducted_by_name || '\u2014')}</td>
        <td class="small">${v.confidential === 'private' ? this.badge('Private', 'bg-warning-subtle text-warning border border-warning-subtle') : ''}</td>
      </tr>`).join('');
  },

  async saveVisit() {
    const subjectType = document.getElementById('cpastVisitSubjectType').value;
    const subjectId = document.getElementById('cpastVisitSubjectId').value.trim();
    const purpose = document.getElementById('cpastVisitPurpose').value.trim();
    const date = document.getElementById('cpastVisitDate').value;
    if (!/^\d+$/.test(subjectId) || !purpose || !date) { showNotification?.('Subject ID, purpose and date are required', 'error'); return; }
    const res = await this.API('/pastoral-visits', 'POST', {
      visit_type: document.getElementById('cpastVisitType').value,
      visit_date: date,
      care_subject_type: subjectType,
      student_id: subjectType === 'student' ? +subjectId : null,
      staff_id: subjectType === 'staff' ? +subjectId : null,
      purpose,
      follow_up_notes: document.getElementById('cpastVisitNotes').value || null,
      next_action_at: document.getElementById('cpastVisitNext').value || null,
      status: document.getElementById('cpastVisitStatus').value,
    });
    if (res?.data?.id || res?.status === 'success') {
      showNotification?.('Visit recorded', 'success');
      bootstrap.Modal.getInstance(document.getElementById('cpastVisitModal'))?.hide();
      this.loadVisits();
    } else {
      showNotification?.(res?.message || 'Failed to record visit', 'error');
    }
  },

  /* ================= bind ================= */
  bind() {
    document.getElementById('cpastRefresh')?.addEventListener('click', () => this.reload());
    document.getElementById('cpastAddGroup')?.addEventListener('click', () => {
      document.getElementById('cpastGroupForm').reset();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('cpastGroupModal')).show();
    });
    document.getElementById('cpastSaveGroup')?.addEventListener('click', () => this.saveGroup());
    document.getElementById('cpastAddMemberBtn')?.addEventListener('click', () => {
      const gid = this.activeGroupId();
      if (gid) this.addGroupMember(gid);
    });
    document.getElementById('cpastSaveGroupAtt')?.addEventListener('click', () => {
      const gid = this.activeGroupId();
      if (gid) this.saveGroupAttendance(gid);
    });
    document.getElementById('cpastGroupLoadAtt')?.addEventListener('click', () => {
      const gid = this.activeGroupId();
      if (gid) {
        const st = this.state.groupStates[gid];
        if (st) { st.attDate = document.getElementById('cpastAttDate').value; this.loadGroupAttendance(gid); }
      }
    });
    document.getElementById('cpastAddVisit')?.addEventListener('click', () => {
      document.getElementById('cpastVisitForm').reset();
      document.getElementById('cpastVisitDate').value = new Date().toISOString().slice(0, 10);
      bootstrap.Modal.getOrCreateInstance(document.getElementById('cpastVisitModal')).show();
    });
    document.getElementById('cpastSaveVisit')?.addEventListener('click', () => this.saveVisit());
    document.getElementById('cpastStudentSearchBtn')?.addEventListener('click', () => this.searchStudents());
    document.getElementById('cpastStudentSearch')?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') this.searchStudents();
    });

    document.addEventListener('click', (ev) => {
      const tab = ev.target.closest('.cpast-tab');
      if (tab) { this.setActiveTab(tab.getAttribute('data-cpast-tab')); return; }
      const gd = ev.target.closest('[data-cpast-role="group-detail"]');
      if (gd) { this.openGroupDetail(gd.getAttribute('data-id')); return; }
      const mr = ev.target.closest('[data-cpast-role="member-remove"]');
      if (mr) { this.removeGroupMember(mr.getAttribute('data-gid'), mr.getAttribute('data-mid')); return; }
      const po = ev.target.closest('[data-cpast-role="profile-open"]');
      if (po) { this.openProfile(po.getAttribute('data-id')); return; }
      const ps = ev.target.closest('[data-cpast-role="profile-save"]');
      if (ps) { this.saveProfile(); return; }
      const ma = ev.target.closest('[data-cpast-role="milestone-add"]');
      if (ma) { this.addMilestone(); return; }
    });
  },

  activeGroupId() {
    const title = document.getElementById('cpastGrpTitle')?.textContent || '';
    const g = this.state.groups.find((x) => x.name === title);
    return g ? String(g.id) : null;
  },

  async reload() {
    await Promise.all([this.loadGroups(), this.loadVisits()]);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.bind();
    await this.reload();
  },
};

window.ChaplaincyPastoralController = ChaplaincyPastoralController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => ChaplaincyPastoralController.init().catch(() => {}));
} else {
  ChaplaincyPastoralController.init().catch(() => {});
}
