document.addEventListener('DOMContentLoaded', async () => { if (window.StaffAccess) await StaffAccess.require('staff.teaching_assignments.view'); });
/**
 * Assign Class Teachers Controller
 * Page: assign_class_teachers.php
 *
 * Lists every open stream of the chosen academic year with its class teacher,
 * so streams still waiting for one stand out. A row's Assign/Change button
 * sets the teacher for that stream; Remove clears it. The year comes from the
 * page's AcademicYearFilter, so next year's class teachers can be set before
 * it opens.
 */
const AssignClassTeachersController = {
  state: {
    rows: [],
    teachers: [],
    yearId: null,
    yearLabel: '',
    editingRow: null,
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.bindEvents();
    // The stream list needs only the year; teachers (for the Assign dialog and
    // the count) load alongside and must not hold the list back.
    const teachers = this.loadTeachers().then(() => this.updateStats(this.state.rows));
    await this.mountYearFilter();
    await Promise.all([this.loadAssignments(), teachers]);
    window.APIRealtime?.register?.(
      "assign_class_teachers",
      { refresh: async () => { await this.loadAssignments(); } },
      ["academic"],
    );
  },

  async mountYearFilter() {
    const select = document.getElementById('academicYearFilter');
    if (!window.AcademicYearFilter || !select) return;
    let mounted = false;
    await window.AcademicYearFilter.mount(select, {
      planning: true,
      onChange: (yearId, year) => {
        this.state.yearId = yearId;
        this.state.yearLabel = year?.year_name || year?.year_code || '';
        if (mounted) this.loadAssignments();
      },
    });
    mounted = true;
  },

  /** A stream has a class teacher only when that teacher is still on staff. */
  hasTeacher(row) {
    return !!row.teacher_id && !row.teacher_missing;
  },

  bindEvents() {
    document.getElementById('searchAssignments')?.addEventListener('input', () => this.render());
    document.getElementById('gradeLevelFilter')?.addEventListener('change', () => this.render());
    document.getElementById('assignmentStatusFilter')?.addEventListener('change', () => this.render());
  },

  async loadTeachers() {
    try {
      const response = await window.API.nodeViews.resolve("academic", "class-teacher-references");
      const sources = response?.data?.sources || response?.sources || {};
      const teachers = Array.isArray(sources.teachers) ? sources.teachers : (sources.teachers?.data || []);
      this.state.teachers = Array.isArray(teachers) ? teachers : [];
    } catch (error) {
      console.warn('Class teacher references view failed; reading teachers directly:', error);
      const teachers = await window.API.apiCall('/staff/teachers', 'GET').catch(() => []);
      this.state.teachers = Array.isArray(teachers) ? teachers : (teachers?.data || []);
    }
    const select = document.getElementById('assignmentTeacherId');
    if (select) {
      select.innerHTML = '<option value="">Select Teacher</option>' + this.state.teachers
        .map((t) => `<option value="${Number(t.id)}">${this.escapeHtml(`${t.first_name || ''} ${t.last_name || ''}`.trim())}</option>`)
        .join('');
    }
  },

  async loadAssignments() {
    const tbody = document.querySelector('#assignmentsTable tbody');
    try {
      const params = this.state.yearId ? { academic_year_id: this.state.yearId } : {};
      const rows = await window.API.apiCall('/academic/class-teachers', 'GET', params);
      this.state.rows = Array.isArray(rows) ? rows : (rows?.data || []);
      this.populateClassFilter();
      this.render();
    } catch (error) {
      console.error('Error loading class teachers:', error);
      this.state.rows = [];
      if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Could not load class teachers.</td></tr>';
      this.updateStats([]);
    }
  },

  populateClassFilter() {
    const select = document.getElementById('gradeLevelFilter');
    if (!select) return;
    const previous = select.value;
    const seen = new Map();
    this.state.rows.forEach((r) => { if (r.class_id && !seen.has(String(r.class_id))) seen.set(String(r.class_id), r.class_name); });
    select.innerHTML = '<option value="">All classes</option>' + [...seen]
      .map(([id, name]) => `<option value="${this.escapeHtml(id)}">${this.escapeHtml(name)}</option>`).join('');
    select.value = seen.has(previous) ? previous : '';
  },

  filteredRows() {
    const classId = document.getElementById('gradeLevelFilter')?.value || '';
    const status = document.getElementById('assignmentStatusFilter')?.value || '';
    const q = (document.getElementById('searchAssignments')?.value || '').trim().toLowerCase();
    return this.state.rows.filter((r) => {
      if (classId && String(r.class_id) !== classId) return false;
      if (status === 'assigned' && !this.hasTeacher(r)) return false;
      if (status === 'unassigned' && this.hasTeacher(r)) return false;
      if (q && ![r.class_name, r.stream_name, r.teacher_name].some((v) => String(v || '').toLowerCase().includes(q))) return false;
      return true;
    });
  },

  render() {
    const tbody = document.querySelector('#assignmentsTable tbody');
    if (!tbody) return;
    const rows = this.filteredRows();
    this.updateStats(this.state.rows);

    if (!this.state.rows.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No streams are set up for ${this.escapeHtml(this.state.yearLabel || 'this year')}.</td></tr>`;
      return;
    }
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No streams match your filters.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map((r, index) => {
      const assigned = this.hasTeacher(r);
      const badge = assigned
        ? '<span class="badge bg-success">Assigned</span>'
        : (r.teacher_missing
          ? '<span class="badge bg-danger" title="The recorded class teacher is no longer on the staff list">Teacher not on staff</span>'
          : '<span class="badge bg-warning text-dark">Still to assign</span>');
      const id = Number(r.id);
      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(r.class_name || '--')}</strong></td>
          <td>${this.escapeHtml(r.stream_name || '--')}</td>
          <td>${assigned ? this.escapeHtml(r.teacher_name || '--') : '<span class="text-muted">Not assigned</span>'}</td>
          <td>${this.escapeHtml(r.teacher_email || '--')}</td>
          <td>${badge}</td>
          <td class="d-print-none">
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-kw-onclick="AssignClassTeachersController.openAssign(${id})" data-year-write>
                <i class="bi bi-${assigned ? 'pencil' : 'person-plus'}"></i> ${assigned ? 'Change' : 'Assign'}
              </button>
              ${r.teacher_id ? `<button class="btn btn-outline-danger" data-kw-onclick="AssignClassTeachersController.removeAssignment(${id})" title="Remove class teacher" aria-label="Remove class teacher" data-year-write>
                <i class="bi bi-trash"></i>
              </button>` : ''}
            </div>
          </td>
        </tr>`;
    }).join('');
  },

  updateStats(rows) {
    const assigned = rows.filter((r) => this.hasTeacher(r)).length;
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    set('totalClassesCount', rows.length);
    set('assignedTeachersCount', assigned);
    set('unassignedClassesCount', rows.length - assigned);
    set('totalTeachersCount', this.state.teachers.length);
  },

  /** Teachers already leading another stream this year, keyed by teacher id. */
  otherStreamsByTeacher(exceptRowId) {
    const map = new Map();
    this.state.rows.forEach((r) => {
      if (!this.hasTeacher(r) || Number(r.id) === Number(exceptRowId)) return;
      map.set(String(r.teacher_id), `${r.class_name}${r.stream_name ? ' ' + r.stream_name : ''}`);
    });
    return map;
  },

  openAssign(rowId) {
    const row = this.state.rows.find((r) => Number(r.id) === Number(rowId));
    if (!row) return;
    this.state.editingRow = row;
    const label = document.getElementById('assignmentStreamLabel');
    if (label) label.textContent = `${row.class_name}${row.stream_name ? ' — ' + row.stream_name : ''} (${this.state.yearLabel || row.academic_year || ''})`;
    const select = document.getElementById('assignmentTeacherId');
    const hint = document.getElementById('assignmentTeacherHint');
    if (select) {
      select.value = this.hasTeacher(row) ? String(row.teacher_id) : '';
      const busy = this.otherStreamsByTeacher(row.id);
      const showHint = () => {
        const other = busy.get(select.value);
        if (hint) hint.textContent = other ? `Already class teacher of ${other} this year.` : '';
      };
      select.onchange = showHint;
      showHint();
    }
    const modal = document.getElementById('assignTeacherModal');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  async saveAssignment() {
    const row = this.state.editingRow;
    const teacherId = document.getElementById('assignmentTeacherId')?.value;
    if (!row) return;
    if (!teacherId) {
      this.showNotification('Choose a teacher', 'warning');
      return;
    }
    try {
      await window.API.apiCall(`/academic/class-teachers/${Number(row.id)}`, 'PUT', {
        teacher_id: teacherId,
        class_id: row.class_id,
        stream_id: row.stream_id,
        academic_year_id: row.academic_year_id,
      });
      bootstrap.Modal.getInstance(document.getElementById('assignTeacherModal'))?.hide();
      this.showNotification('Class teacher saved', 'success');
      this.state.editingRow = null;
      await this.loadAssignments();
    } catch (error) {
      this.showNotification(error?.message || 'Failed to save the class teacher', 'error');
    }
  },

  async removeAssignment(rowId) {
    const row = this.state.rows.find((r) => Number(r.id) === Number(rowId));
    const what = row ? `${row.class_name}${row.stream_name ? ' ' + row.stream_name : ''}` : 'this stream';
    if (!(await window.confirmAction('Remove class teacher', `Remove the class teacher from ${what}?`, { confirmText: 'Remove', danger: true }))) return;
    try {
      await window.API.apiCall(`/academic/class-teachers/${Number(rowId)}`, 'DELETE');
      this.showNotification('Class teacher removed', 'success');
      await this.loadAssignments();
    } catch (error) {
      this.showNotification(error?.message || 'Failed to remove the class teacher', 'error');
    }
  },

  async exportAssignments() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) {
      this.showNotification('You do not have permission to export', 'warning');
      return;
    }
    const rows = this.filteredRows();
    if (!rows.length) {
      this.showNotification('Nothing to export', 'info');
      return;
    }
    const cell = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [['Academic year', 'Class', 'Stream', 'Class teacher', 'Teacher email', 'Status'].map(cell).join(',')];
    rows.forEach((r) => lines.push([
      this.state.yearLabel || r.academic_year, r.class_name, r.stream_name,
      this.hasTeacher(r) ? r.teacher_name : '', this.hasTeacher(r) ? (r.teacher_email || '') : '',
      this.hasTeacher(r) ? 'Assigned' : (r.teacher_missing ? 'Teacher not on staff' : 'Still to assign'),
    ].map(cell).join(',')));
    const safeYear = String(this.state.yearLabel || 'year').replace(/[^0-9A-Za-z-]+/g, '-');
    await window.KingswayFileLifecycle.exportText('﻿' + lines.join('\r\n'), `class_teachers_${safeYear}.csv`, 'text/csv;charset=utf-8');
  },

  printAssignments() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
      this.showNotification('You do not have permission to print', 'warning');
      return;
    }
    window.print();
  },

  async refresh() {
    await this.loadAssignments();
  },

  escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  },

  showNotification(message, type = 'info') {
    if (typeof window.showNotification === 'function') window.showNotification(message, type);
  }
};

document.addEventListener('DOMContentLoaded', () => AssignClassTeachersController.init());

window.AssignClassTeachersController = AssignClassTeachersController;
