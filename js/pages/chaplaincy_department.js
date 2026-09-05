/**
 * chaplaincy_department.js — Chaplaincy Department Controller.
 * Team roster, borrowed staff members, parent/community volunteers, and the
 * tabbed "Assign Ministry Member" modal (Staff | Parents | Students) that
 * stages every selection locally and submits all three registries in one shot.
 */
const ChaplaincyDepartmentController = {
  state: {
    dept: null,
    members: [],
    volunteers: [],
    roles: [],
    staffDirectory: [],
    parentDirectory: [],
    studentDirectory: [],
    studentFilters: { level: '', grade: '', student_type: '', q: '' },
    staffQ: '',
    parentQ: '',
    stage: null,
    loading: { staff: false, parents: false, students: false },
  },

  STAGE_KEY: 'kwa.chaplaincy.assign.stage',
  DEPT_HEAD_CODES: ['dept_head', 'dept-head', 'department_head'],
  MAX_STUDENTS: 600,

  API(path, method = 'GET', data = null, params = null) {
    return window.callAPI('/chaplaincy' + path, method, data, params);
  },

  esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString();
  },

  badge(label, cls = 'bg-secondary') {
    return `<span class="badge ${cls}">${this.esc(label)}</span>`;
  },

  /* ---------- data loading ---------- */
  async load() {
    const [teamRes, rolesRes, volsRes] = await Promise.all([
      this.API('/team').catch(() => null),
      this.API('/team-roles').catch(() => null),
      this.API('/volunteers').catch(() => null),
    ]);
    const team = teamRes?.data || teamRes || {};
    this.state.dept = team;
    this.state.members = (team.members || []).filter((m) => m.staff_id);
    this.state.roles = rolesRes?.data || rolesRes || [];
    this.state.volunteers = volsRes?.data || volsRes || [];
    this.render();
  },

  /* ---------- rendering ---------- */
  render() {
    this.renderHead();
    this.renderMembers();
    this.renderRoles();
    this.renderVolunteers();
  },

  renderHead() {
    const el = document.getElementById('chdHeadName');
    if (!el) return;
    const head = this.state.dept?.head;
    el.textContent = head ? head.staff_name : 'Unassigned';
    const sub = document.getElementById('chdHeadStaffNo');
    if (sub) sub.textContent = head ? (head.staff_no || '') : 'Assign a Chaplain to lead the department';
  },

  renderMembers() {
    const tb = document.getElementById('chdMembers');
    if (!tb) return;
    const list = this.state.members;
    const cnt = document.getElementById('chdMemberCount');
    if (cnt) cnt.textContent = list.length;
    if (!list.length) {
      tb.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No team members assigned yet.</td></tr>';
      return;
    }
    tb.innerHTML = list.map((m) => `
      <tr>
        <td class="fw-semibold small">${this.esc(m.staff_name || m.staff_no || '\u2014')}</td>
        <td class="small">${this.esc(m.staff_no || '\u2014')}</td>
        <td class="small">${this.badge(m.team_role || 'Member', 'bg-soft text-primary border border-primary-subtle')}</td>
        <td class="small text-muted">${this.esc(m.primary_department || '\u2014')}</td>
        <td class="small text-muted">${this.fmtDate(m.effective_from)}${m.effective_to ? ' \u2192 ' + this.fmtDate(m.effective_to) : ''}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" data-mf-action="unassign" data-assignment="${this.esc(m.assignment_id)}" title="End assignment"><i class="bi bi-x-circle"></i></button>
        </td>
      </tr>`).join('');
  },

  renderRoles() {
    const wrap = document.getElementById('chdRoles');
    if (!wrap) return;
    const roles = this.state.roles;
    if (!roles.length) { wrap.innerHTML = '<div class="text-center text-muted py-3">No ministry roles defined.</div>'; return; }
    wrap.innerHTML = '<div class="d-flex flex-wrap gap-2 p-2">' + roles.map((r) =>
      `<span class="badge bg-soft text-dark border">${this.esc(r.name)}</span>`
    ).join('') + '</div>';
  },

  renderVolunteers() {
    const tb = document.getElementById('chdVolunteers');
    if (!tb) return;
    const list = this.state.volunteers;
    const cnt = document.getElementById('chdVolunteerCount');
    if (cnt) cnt.textContent = list.length;
    if (!list.length) {
      tb.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No volunteers registered.</td></tr>';
      return;
    }
    tb.innerHTML = list.map((v) => `
      <tr>
        <td class="fw-semibold small">${this.esc(v.full_name)}</td>
        <td class="small text-muted">${this.esc(v.role_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(v.contact_phone || v.contact_email || '\u2014')}</td>
        <td class="small">${v.police_clearance_verified
          ? this.badge('Verified', 'bg-success-subtle text-success border border-success-subtle')
          : this.badge('Pending', 'bg-warning-subtle text-warning border border-warning-subtle')}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger" data-mf-action="unvolunteer" data-id="${this.esc(v.id)}" title="Deactivate"><i class="bi bi-person-x"></i></button>
        </td>
      </tr>`).join('');
  },

  /* =================================================================
   * Assign Member modal — tabbed Staff / Parents / Students staging
   * ================================================================= */

  isDeptHead(role) {
    const code = String(role?.code || '').toLowerCase();
    return this.DEPT_HEAD_CODES.includes(code) ||
      /department *head|dept *head/i.test(String(role?.name || ''));
  },

  assignableRoles() {
    const roles = Array.isArray(this.state.roles) ? this.state.roles : [];
    return roles.filter((r) => !this.isDeptHead(r));
  },

  emptyStage() {
    return {
      staff: { role_id: '', ids: [] },
      parents: { role_id: '', ids: [] },
      students: { role_id: '', ids: [] },
      from: '',
      to: '',
      year: '0',
    };
  },

  readStage() {
    let stage = null;
    try {
      stage = JSON.parse(localStorage.getItem(this.STAGE_KEY) || 'null');
    } catch (e) { stage = null; }
    const base = this.emptyStage();
    if (!stage || typeof stage !== 'object') return base;
    return {
      staff: {
        role_id: stage.staff?.role_id ?? base.staff.role_id,
        ids: Array.isArray(stage.staff?.ids) ? stage.staff.ids : [],
      },
      parents: {
        role_id: stage.parents?.role_id ?? base.parents.role_id,
        ids: Array.isArray(stage.parents?.ids) ? stage.parents.ids : [],
      },
      students: {
        role_id: stage.students?.role_id ?? base.students.role_id,
        ids: Array.isArray(stage.students?.ids) ? stage.students.ids : [],
      },
      from: stage.from || '',
      to: stage.to || '',
      year: stage.year || '0',
    };
  },

  writeStage() {
    try {
      localStorage.setItem(this.STAGE_KEY, JSON.stringify(this.state.stage));
    } catch (e) { /* localStorage unavailable — staging stays in memory */ }
  },

  clearStage() {
    try { localStorage.removeItem(this.STAGE_KEY); } catch (e) {}
    this.state.stage = this.emptyStage();
  },

  stageCounts() {
    const s = this.state.stage || this.emptyStage();
    return {
      staff: (s.staff?.ids || []).length,
      parents: (s.parents?.ids || []).length,
      students: (s.students?.ids || []).length,
    };
  },

  openMemberModal() {
    this.state.stage = this.readStage();
    this.state.staffQ = '';
    this.state.parentQ = '';
    this.state.studentFilters = { level: '', grade: '', student_type: '', q: '' };
    this.populateRoleSelects();
    this.populateStudentFilterMeta();
    const yearSel = document.getElementById('chdStudentYear');
    if (yearSel) yearSel.value = this.state.stage.year || '0';
    const fromEl = document.getElementById('chdMemberFrom'); if (fromEl) fromEl.value = this.state.stage.from || '';
    const toEl = document.getElementById('chdMemberTo'); if (toEl) toEl.value = this.state.stage.to || '';
    this.populateYears();
    this.renderAssignmentLists();
    this.updateAssignmentSummary();
    this.loadAllDirectories();
    const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('chdMemberModal'));
    m.show();
    // Reset tab to Staff on every open for a predictable starting point.
    const staffTab = document.getElementById('chdTabStaffBtn');
    if (staffTab && window.bootstrap?.Tab) bootstrap.Tab.getOrCreateInstance(staffTab).show();
  },

  populateRoleSelects() {
    const roles = this.assignableRoles();
    const opts = (selected) => '<option value="">— choose role —</option>' + roles
      .map((r) => `<option value="${this.esc(r.id)}" ${String(r.id) === selected ? 'selected' : ''}>${this.esc(r.name)}</option>`)
      .join('');
    const staffEl = document.getElementById('chdStaffRole'); if (staffEl) staffEl.innerHTML = opts(this.state.stage?.staff?.role_id || '');
    const parentEl = document.getElementById('chdParentRole'); if (parentEl) parentEl.innerHTML = opts(this.state.stage?.parents?.role_id || '');
    const studentEl = document.getElementById('chdStudentRole'); if (studentEl) studentEl.innerHTML = opts(this.state.stage?.students?.role_id || '');
  },

  populateStudentFilterMeta() {
    const dir = this.state.studentDirectory;
    const typeSel = document.getElementById('chdStudentType');
    typeSel.innerHTML = '<option value="">All types</option>'
      + ['DAY', 'BOARD', 'WEEKLY'].map((t) => `<option value="${t}">${t}</option>`).join('');
    const classSel = document.getElementById('chdStudentGrade');
    classSel.innerHTML = '<option value="">All classes</option>';
    if (dir.length) {
      const classes = [...new Map(dir.filter((s) => s.class_id).map((s) => [s.class_id, { id: s.class_id, name: s.class_name }])).values()]
        .sort((a, b) => String(a.name).localeCompare(String(b.name)));
      classSel.innerHTML += classes
        .map((c) => `<option value="${this.esc(c.id)}">${this.esc(c.name)}</option>`).join('');
    }
    const levelSel = document.getElementById('chdStudentLevel');
    levelSel.innerHTML = '<option value="">All levels</option>'
      + this.schoolLevels().map((l) => `<option value="${this.esc(l)}">${this.esc(l)}</option>`).join('');
  },

  /* School-level band derived from the actual classes present in the data
     (Kenyan CBC: ECD / Lower Primary / Upper Primary / Junior Secondary). */
  schoolLevels() {
    const bands = ['Pre-Primary (ECD)', 'Lower Primary', 'Upper Primary', 'Junior Secondary'];
    const used = new Set();
    this.state.studentDirectory.forEach((s) => {
      const band = this.classToLevel(s.class_name);
      if (band) used.add(band);
    });
    return used.size ? bands.filter((b) => used.has(b)) : bands;
  },

  classToLevel(name) {
    const n = String(name || '').toLowerCase();
    if (n.includes('playgroup') || /^pp\s*\d?$/.test(n.trim())) return 'Pre-Primary (ECD)';
    const m = n.match(/grade\s*(\d+)/);
    const g = m ? parseInt(m[1], 10) : 0;
    if (g >= 1 && g <= 3) return 'Lower Primary';
    if (g >= 4 && g <= 6) return 'Upper Primary';
    if (g >= 7 && g <= 9) return 'Junior Secondary';
    return '';
  },

  async populateYears() {
    const sel = document.getElementById('chdStudentYear');
    if (!sel) return;
    try {
      const res = await window.API.apiCall('/academic/years-list', 'GET');
      const data = res?.data?.data ?? res?.data ?? res ?? [];
      const years = Array.isArray(data) ? data : [];
      const active = years.filter((y) => String(y.status || '').toLowerCase() !== 'archived');
      sel.innerHTML = '<option value="">Auto (from date)</option>' + active
        .map((y) => `<option value="${this.esc(y.id)}">${this.esc(y.year_name || y.name)}</option>`).join('');
      sel.value = this.state.stage?.year || '';
    } catch (e) { /* year auto-resolution still works server side */ }
  },

  /* -------- directories -------- */
  async loadAllDirectories() {
    await Promise.allSettled([
      this.loadStaffDirectory(),
      this.loadParentDirectory(),
      this.loadStudentDirectory(),
    ]);
    this.populateStudentFilterMeta();
    this.renderAssignmentLists();
    this.updateAssignmentSummary();
  },

  async loadStaffDirectory() {
    if (this.state.staffDirectory.length || this.state.loading.staff) return;
    this.state.loading.staff = true;
    try {
      const res = await window.API.staff.index({ limit: 500 });
      const payload = res?.data ?? res ?? {};
      this.state.staffDirectory = Array.isArray(payload) ? payload
        : Array.isArray(payload.staff) ? payload.staff
        : Array.isArray(payload.data?.staff) ? payload.data.staff : [];
    } catch (e) {
      this.state.staffDirectory = [];
    } finally {
      this.state.loading.staff = false;
    }
  },

  async loadParentDirectory() {
    if (this.state.parentDirectory.length || this.state.loading.parents) return;
    this.state.loading.parents = true;
    try {
      const res = await window.callAPI('/students/parents/list', 'GET', null, { limit: 300 });
      const payload = res?.data ?? res ?? {};
      this.state.parentDirectory = Array.isArray(payload) ? payload
        : Array.isArray(payload.parents) ? payload.parents
        : Array.isArray(payload.data) ? payload.data
        : Array.isArray(payload.data?.parents) ? payload.data.parents : [];
    } catch (e) {
      this.state.parentDirectory = [];
    } finally {
      this.state.loading.parents = false;
    }
  },

  async loadStudentDirectory() {
    if (this.state.studentDirectory.length || this.state.loading.students) return;
    this.state.loading.students = true;
    const seen = new Map();
    try {
      let page = 1, total = Infinity;
      while (seen.size < this.MAX_STUDENTS && seen.size < total) {
        const res = await window.API.students.list({ limit: 100, page });
        const payload = res?.data?.data ?? res?.data ?? res ?? {};
        const rows = Array.isArray(payload) ? payload
          : Array.isArray(payload.students) ? payload.students
          : Array.isArray(payload.data?.students) ? payload.data.students : [];
        if (!rows.length) break;
        rows.forEach((s) => { const id = Number(s.id); if (id > 0) seen.set(id, s); });
        total = payload?.pagination?.total ?? total;
        if (rows.length < 100) break;
        page++;
      }
      this.state.studentDirectory = [...seen.values()];
    } catch (e) {
      this.state.studentDirectory = [...seen.values()];
    } finally {
      this.state.loading.students = false;
    }
  },

  /* -------- filtered lists -------- */
  filteredStaff() {
    const q = this.state.staffQ.trim().toLowerCase();
    if (!q) return this.state.staffDirectory;
    return this.state.staffDirectory.filter((p) => `${p.full_name || ''} ${p.first_name || ''} ${p.last_name || ''} ${p.staff_no || ''} ${p.phone_number || ''}`
      .toLowerCase().includes(q));
  },

  filteredParents() {
    const q = this.state.parentQ.trim().toLowerCase();
    if (!q) return this.state.parentDirectory;
    return this.state.parentDirectory.filter((p) => `${p.full_name || ''} ${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''} ${p.phone_1 || p.phone_number || ''} ${p.email || ''}`
      .toLowerCase().includes(q));
  },

  filteredStudents() {
    const f = this.state.studentFilters;
    return this.state.studentDirectory.filter((s) => {
      if (f.level && this.classToLevel(s.class_name) !== f.level) return false;
      if (f.grade && String(s.class_id) !== String(f.grade)) return false;
      if (f.student_type && String(s.student_type_code || '').toUpperCase() !== f.student_type) return false;
      if (f.q) {
        const q = f.q.toLowerCase();
        const hay = `${s.full_name || ''} ${s.first_name || ''} ${s.last_name || ''} ${s.admission_no || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });
  },

  /* -------- list rendering -------- */
  renderAssignmentLists() {
    this.renderPersonList('staff');
    this.renderPersonList('parents');
    this.renderPersonList('students');
  },

  renderPersonList(type) {
    const cfg = {
      staff: {
        listEl: 'chdStaffList', countEl: 'chdStaffCount', allEl: 'chdStaffAll',
        rows: () => this.filteredStaff(),
        id: (p) => Number(p.id),
        label: (p) => p.full_name || `${p.first_name || ''} ${p.last_name || ''}`.trim() || `#${p.id}`,
        sub: (p) => p.staff_no || p.primary_department || '',
        key: (p) => p.staff_no || `id:${p.id}`,
      },
      parents: {
        listEl: 'chdParentList', countEl: 'chdParentCount', allEl: 'chdParentAll',
        rows: () => this.filteredParents(),
        id: (p) => Number(p.id),
        label: (p) => p.full_name || `${p.first_name || ''} ${p.last_name || ''}`.trim() || `#${p.id}`,
        sub: (p) => p.children_count ? `${p.children_count} child${p.children_count > 1 ? 'ren' : ''}` : (p.phone_1 || ''),
        key: (p) => p.phone_1 || `id:${p.id}`,
      },
      students: {
        listEl: 'chdStudentList', countEl: 'chdStudentCount', allEl: 'chdStudentAll',
        rows: () => this.filteredStudents(),
        id: (s) => Number(s.id),
        label: (s) => s.full_name || `${s.first_name || ''} ${s.last_name || ''}`.trim() || `#${s.id}`,
        sub: (s) => `${s.class_name || '—'}${s.stream_name ? ' · ' + s.stream_name : ''}`,
        key: (s) => s.admission_no || `id:${s.id}`,
      },
    }[type];
    const listEl = document.getElementById(cfg.listEl);
    if (!listEl) return;
    if (this.state.loading[type === 'staff' ? 'staff' : type === 'parents' ? 'parents' : 'students'] && !this.state[type === 'staff' ? 'staffDirectory' : type === 'parents' ? 'parentDirectory' : 'studentDirectory'].length) {
      listEl.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div>';
      return;
    }
    const rows = cfg.rows();
    const ids = this.state.stage?.[type]?.ids || [];
    const countEl = document.getElementById(cfg.countEl);
    if (countEl) countEl.textContent = `${rows.length} shown`;
    if (!rows.length) {
      listEl.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No records to show.</div>';
      return;
    }
    listEl.innerHTML = rows.map((p) => {
      const id = cfg.id(p);
      const checked = ids.includes(id) ? ' checked' : '';
      return `
        <label class="chd-person-option d-flex align-items-center gap-2 px-3 py-2 border-bottom mb-0${ids.includes(id) ? ' is-checked' : ''}">
          <input type="checkbox" class="form-check-input chd-person-check" data-type="${type}" data-id="${id}" ${checked}>
          <span class="fw-semibold small">${this.esc(cfg.label(p))}</span>
          <span class="badge bg-secondary-subtle text-secondary">${this.esc(cfg.key(p))}</span>
          <span class="text-muted small ms-auto">${this.esc(cfg.sub(p))}</span>
        </label>`;
    }).join('');
  },

  updateAssignmentSummary() {
    const c = this.stageCounts();
    const el = document.getElementById('chdStageStaff');
    if (el) el.textContent = `Staff ${c.staff}`;
    const p = document.getElementById('chdStageParents');
    if (p) p.textContent = `Parents ${c.parents}`;
    const st = document.getElementById('chdStageStudents');
    if (st) st.textContent = `Students ${c.students}`;
    const total = c.staff + c.parents + c.students;
    const sum = document.getElementById('chdAssignSummary');
    if (sum) {
      sum.textContent = total
        ? `Assigning ${c.staff} staff, ${c.parents} parents, ${c.students} students`
        : 'Nothing staged yet';
    }
    const btn = document.getElementById('chdSaveMember');
    if (btn) btn.innerHTML = total
      ? `<i class="bi bi-check2-circle me-1"></i>Assign Selected (${total})`
      : '<i class="bi bi-check2-circle me-1"></i>Assign Selected';
  },

  togglePerson(type, id, checked) {
    const group = this.state.stage[type];
    if (checked) {
      if (!group.ids.includes(id)) group.ids.push(id);
    } else {
      group.ids = group.ids.filter((x) => x !== id);
    }
    this.writeStage();
    this.renderPersonList(type);
    this.updateAssignmentSummary();
  },

  toggleSelectAll(type) {
    const cfg = {
      staff: () => this.filteredStaff(), parents: () => this.filteredParents(), students: () => this.filteredStudents(),
    }[type];
    const allEl = { staff: 'chdStaffAll', parents: 'chdParentAll', students: 'chdStudentAll' }[type];
    const el = document.getElementById(allEl);
    if (!el) return;
    const rows = cfg();
    const group = this.state.stage[type];
    if (!rows.length) return;
    if (el.checked) {
      rows.forEach((p) => { const id = Number(p.id); if (!group.ids.includes(id)) group.ids.push(id); });
    } else {
      const ids = new Set(rows.map((p) => Number(p.id)));
      group.ids = group.ids.filter((id) => !ids.has(id));
    }
    this.writeStage();
    this.renderPersonList(type);
    this.updateAssignmentSummary();
  },

  /* -------- submit (one POST to the bulk endpoint) -------- */
  async saveMember(e) {
    e?.preventDefault();
    const stage = this.state.stage;
    const counts = this.stageCounts();

    const roleFor = (type) => {
      const roleId = stage[type].role_id;
      const role = this.state.roles.find((r) => String(r.id) === String(roleId));
      if (!role && counts[type] > 0) return null;
      return role;
    };

    const staffRole = roleFor('staff');
    const parentRole = roleFor('parents');
    const studentRole = roleFor('students');
    if (counts.staff > 0 && !staffRole) { showNotification?.('Choose a ministry role for the selected staff', 'error'); return; }
    if (counts.parents > 0 && !parentRole) { showNotification?.('Choose a ministry role for the selected parents', 'error'); return; }
    if (counts.students > 0 && !studentRole) { showNotification?.('Choose a ministry role for the selected students', 'error'); return; }
    if (counts.staff + counts.parents + counts.students === 0) { showNotification?.('Select staff, parents or students first', 'error'); return; }

    const from = stage.from || (document.getElementById('chdMemberFrom')?.value || new Date().toISOString().slice(0, 10));
    const to = stage.to || (document.getElementById('chdMemberTo')?.value || undefined);
    const year = stage.year || '0';

    const payload = {};
    if (counts.staff) payload.staff = { role_id: stage.staff.role_id, ids: stage.staff.ids, effective_from: from, effective_to: to || undefined };
    if (counts.parents) payload.parents = { role_id: stage.parents.role_id, ids: stage.parents.ids };
    if (counts.students) payload.students = { role_id: stage.students.role_id, ids: stage.students.ids, effective_from: from };
    payload.effective_from = from;
    payload.effective_to = to || null;
    if (year && year !== '0') payload.academic_year_id = Number(year);

    const btn = document.getElementById('chdSaveMember');
    if (btn) btn.disabled = true;
    try {
      const res = await this.API('/team/bulk-assign', 'POST', payload);
      const data = res || {};
      const a = data.assigned || {};
      const skipped = Array.isArray(data.skipped) ? data.skipped.length : 0;
      const msg = `Assigned ${a.staff || 0} staff, ${a.parents || 0} parents, ${a.students || 0} students` +
        (skipped ? ` (${skipped} skipped)` : '');
      this.clearStage();
      bootstrap.Modal.getInstance(document.getElementById('chdMemberModal'))?.hide();
      this.load();
      showNotification?.(msg, 'success');
    } catch (err) {
      showNotification?.(err?.message || 'The server could not assign the selection', 'error');
    } finally {
      if (btn) btn.disabled = false;
    }
  },

  /* ---------- volunteer modal (single parent/community volunteer) ---------- */
  openVolunteerModal() {
    const sel = document.getElementById('chdVolRole');
    if (sel) {
      const roles = this.assignableRoles();
      sel.innerHTML = roles.map((r) => `<option value="${this.esc(r.id)}">${this.esc(r.name)}</option>`).join('');
    }
    document.getElementById('chdVolunteerForm')?.reset();
    const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('chdVolunteerModal'));
    m.show();
  },

  async saveVolunteer(e) {
    e?.preventDefault();
    const fullName = document.getElementById('chdVolName')?.value?.trim();
    if (!fullName) { showNotification?.('Volunteer name is required', 'error'); return; }
    const data = {
      full_name: fullName,
      contact_phone: document.getElementById('chdVolPhone')?.value || null,
      contact_email: document.getElementById('chdVolEmail')?.value || null,
      role_id: document.getElementById('chdVolRole')?.value || null,
      linked_student_id: document.getElementById('chdVolStudent')?.value || null,
      police_clearance_verified: document.getElementById('chdVolClearance')?.checked || false,
      notes: document.getElementById('chdVolNotes')?.value || null,
    };
    try {
      await this.API('/volunteers', 'POST', data);
      showNotification?.('Volunteer registered', 'success');
      bootstrap.Modal.getInstance(document.getElementById('chdVolunteerModal'))?.hide();
      this.load();
    } catch (err) {
      showNotification?.(err?.message || 'Failed to register volunteer', 'error');
    }
  },

  async unassignMember(assignmentId) {
    if (!confirm('End this team member\'s Chaplaincy assignment?')) return;
    try {
      await this.API(`/team/member/${assignmentId}`, 'PUT', { team_role: null, effective_to: new Date().toISOString().slice(0, 10) });
      showNotification?.('Assignment ended', 'success');
      this.load();
    } catch (err) {
      showNotification?.(err?.message || 'Failed to end assignment', 'error');
    }
  },

  async unvolunteer(id) {
    if (!confirm('Deactivate this volunteer?')) return;
    try {
      await this.API(`/volunteers/${id}`, 'DELETE');
      showNotification?.('Volunteer deactivated', 'success');
      this.load();
    } catch (err) {
      showNotification?.(err?.message || 'Failed to deactivate volunteer', 'error');
    }
  },

  /* ---------- events ---------- */
  bind() {
    (document.getElementById('chdRefresh'))?.addEventListener('click', () => this.load());
    (document.getElementById('chdAddMember'))?.addEventListener('click', () => this.openMemberModal());
    (document.getElementById('chdAddVolunteer'))?.addEventListener('click', () => this.openVolunteerModal());
    (document.getElementById('chdSaveMember'))?.addEventListener('click', (e) => this.saveMember(e));
    (document.getElementById('chdSaveVolunteer'))?.addEventListener('click', (e) => this.saveVolunteer(e));

    // Person pickers (delegated) — toggling a checkbox stages the person.
    document.getElementById('chdMemberModal')?.addEventListener('change', (ev) => {
      const src = ev.target;
      if (src.classList?.contains('chd-person-check')) {
        this.togglePerson(src.dataset.type, Number(src.dataset.id), src.checked);
        return;
      }
      const map = { chdStaffAll: 'staff', chdParentAll: 'parents', chdStudentAll: 'students' };
      if (map[src.id]) this.toggleSelectAll(map[src.id]);
    });

    // Filters
    (document.getElementById('chdStaffFilter'))?.addEventListener('input', (e) => { this.state.staffQ = e.target.value || ''; this.renderPersonList('staff'); });
    (document.getElementById('chdParentFilter'))?.addEventListener('input', (e) => { this.state.parentQ = e.target.value || ''; this.renderPersonList('parents'); });
    (document.getElementById('chdStudentFilter'))?.addEventListener('input', (e) => { this.state.studentFilters.q = e.target.value || ''; this.renderPersonList('students'); });
    (document.getElementById('chdStudentLevel'))?.addEventListener('change', (e) => { this.state.studentFilters.level = e.target.value || ''; this.renderPersonList('students'); });
    (document.getElementById('chdStudentGrade'))?.addEventListener('change', (e) => { this.state.studentFilters.grade = e.target.value || ''; this.renderPersonList('students'); });
    (document.getElementById('chdStudentType'))?.addEventListener('change', (e) => { this.state.studentFilters.student_type = e.target.value || ''; this.renderPersonList('students'); });

    // Role + date changes restage locally.
    const wireStage = (id, key, scalar = false) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', () => {
        if (scalar) {
          this.state.stage[key] = el.value;
        } else if (this.state.stage[key]) {
          this.state.stage[key].role_id = el.value;
        }
        this.writeStage();
        this.updateAssignmentSummary();
      });
    };
    wireStage('chdStaffRole', 'staff');
    wireStage('chdParentRole', 'parents');
    wireStage('chdStudentRole', 'students');
    wireStage('chdStudentYear', 'year', true);
    wireStage('chdMemberFrom', 'from', true);
    wireStage('chdMemberTo', 'to', true);
    (document.getElementById('chdStageClear'))?.addEventListener('click', () => {
      this.clearStage();
      const from = document.getElementById('chdMemberFrom'); if (from) from.value = '';
      const to = document.getElementById('chdMemberTo'); if (to) to.value = '';
      this.populateRoleSelects();
      this.renderAssignmentLists();
      this.updateAssignmentSummary();
    });

    document.addEventListener('click', (ev) => {
      const unassign = ev.target.closest('[data-mf-action="unassign"]');
      if (unassign) { this.unassignMember(unassign.getAttribute('data-assignment')); return; }
      const unvol = ev.target.closest('[data-mf-action="unvolunteer"]');
      if (unvol) { this.unvolunteer(unvol.getAttribute('data-id')); return; }
    });
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.bind();
    await this.load();
  },
};

window.ChaplaincyDepartmentController = ChaplaincyDepartmentController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => ChaplaincyDepartmentController.init().catch(() => {}));
} else {
  ChaplaincyDepartmentController.init().catch(() => {});
}