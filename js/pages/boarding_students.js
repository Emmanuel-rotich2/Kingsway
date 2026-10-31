/**
 * Boarding Students page controller.
 * The PHP template owns the 13-column roster and its filter/modal markup.
 */
const boardingStudentsController = {
  state: {
    referenceCache: null,
    students: [],
    academicYears: [],
    classes: [],
    streams: [],
    dormitories: [],
    selectedStudentId: null,
    loadSequence: 0,
    eventsBound: false,
  },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  byId: (id) => document.getElementById(id),
  esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
  },

  unwrapList(value, keys = []) {
    let current = value;
    for (let depth = 0; depth < 6; depth += 1) {
      if (Array.isArray(current)) return current;
      if (!current || typeof current !== 'object') return [];
      const nextKey = [...keys, 'items', 'rows', 'records', 'data'].find((key) => current[key] !== undefined);
      if (!nextKey) return [];
      current = current[nextKey];
    }
    return Array.isArray(current) ? current : [];
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read('boarding', 'boarding-students-references');
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn('Boarding Students reference composition failed; using PHP reads:', error);
      this.state.referenceCache = null;
    }
  },

  async loadReferences() {
    const cache = this.state.referenceCache || {};
    let meta = null;
    if (!['academicYears', 'classes', 'streams', 'dormitories'].every((key) => cache[key] !== undefined)) {
      meta = await this.API('GET', '/api/students/boarding-meta');
    }
    meta = meta?.data || meta || {};
    this.state.academicYears = this.unwrapList(cache.academicYears ?? meta.academic_years, ['academic_years']);
    this.state.classes = this.unwrapList(cache.classes ?? meta.classes, ['classes']);
    this.state.streams = this.unwrapList(cache.streams ?? meta.streams, ['streams']);
    this.state.dormitories = this.unwrapList(cache.dormitories ?? meta.dormitories, ['dormitories']);
    this.populateReferenceFilters();
  },

  populateReferenceFilters() {
    const fill = (id, rows, label, firstLabel) => {
      const select = this.byId(id);
      if (!select) return;
      const previous = select.value;
      select.innerHTML = `<option value="">${firstLabel}</option>` + rows.map((row) =>
        `<option value="${Number(row.id) || ''}">${this.esc(label(row))}</option>`
      ).join('');
      if (rows.some((row) => String(row.id) === previous)) select.value = previous;
    };
    fill('academicYearFilter', this.state.academicYears,
      (row) => row.year_name || row.year_code || `Year ${row.id}`, 'Current academic year');
    fill('classFilter', this.state.classes, (row) => row.name || row.class_name || '', 'All Classes');
    fill('streamFilter', this.state.streams, (row) => row.name || row.stream_name || '', 'All Streams');
    fill('dormitoryFilter', this.state.dormitories,
      (row) => row.dormitory_name || row.name || '', 'All Dormitories');
    fill('rollCallDormitory', this.state.dormitories,
      (row) => row.dormitory_name || row.name || '', 'All Dormitories');
    fill('assignDormitory', this.state.dormitories,
      (row) => row.dormitory_name || row.name || '', 'Select Dormitory');
    fill('boardingNoteStudent', this.state.students,
      (row) => `${row.admission_no || ''} ${row.full_name || ''}`.trim(), 'Select Student');
    fill('exeatStudent', this.state.students,
      (row) => `${row.admission_no || ''} ${row.full_name || ''}`.trim(), 'Select Student');

    const currentYear = this.state.academicYears.find((year) =>
      year.is_current === true || Number(year.is_current) === 1
    );
    const yearFilter = this.byId('academicYearFilter');
    if (yearFilter && !yearFilter.value && currentYear) yearFilter.value = String(currentYear.id);
  },

  async load() {
    const sequence = ++this.state.loadSequence;
    const loading = this.byId('studentsLoading');
    const errorBox = this.byId('studentsError');
    const forbidden = this.byId('studentsForbidden');
    const empty = this.byId('studentsEmpty');
    loading?.classList.remove('d-none');
    errorBox?.classList.add('d-none');
    forbidden?.classList.add('d-none');
    empty?.classList.add('d-none');

    const params = {};
    const year = this.byId('academicYearFilter')?.value;
    const dormitory = this.byId('dormitoryFilter')?.value;
    const search = this.byId('searchBox')?.value.trim();
    if (year) params.academic_year_id = year;
    if (dormitory) params.dormitory_id = dormitory;
    if (search) params.search = search;

    try {
      const response = await this.API('GET', '/api/students/boarding-students', null, params);
      if (sequence !== this.state.loadSequence) return;
      this.state.students = this.unwrapList(response, ['students']);
      this.render();
    } catch (error) {
      if (sequence !== this.state.loadSequence) return;
      const isForbidden = Number(error?.status || error?.statusCode) === 403;
      (isForbidden ? forbidden : errorBox)?.classList.remove('d-none');
      if (!isForbidden && errorBox) errorBox.textContent = error?.message || 'Boarding students could not be loaded. Try refreshing.';
      const body = this.byId('studentsTableBody');
      if (body) body.innerHTML = `<tr><td colspan="13" class="text-center text-danger py-4">${this.esc(isForbidden ? 'You do not have permission to access boarding data.' : 'Boarding students could not be loaded.')}</td></tr>`;
      console.error('Failed to load boarding students:', error);
    } finally {
      if (sequence === this.state.loadSequence) loading?.classList.add('d-none');
    }
  },

  filteredStudents() {
    const value = (id) => this.byId(id)?.value || '';
    const classId = value('classFilter');
    const streamId = value('streamFilter');
    const gender = value('genderFilter').toLowerCase();
    const bedStatus = value('bedStatusFilter');
    const boardingStatus = value('boardingStatusFilter');
    const rollCallStatus = value('rollCallStatusFilter');
    return this.state.students.filter((student) => {
      if (classId && String(student.class_id) !== classId) return false;
      if (streamId && String(student.stream_id) !== streamId) return false;
      if (gender && String(student.gender || '').toLowerCase() !== gender) return false;
      const hasBed = Boolean(String(student.bed_number || '').trim());
      if (bedStatus === 'assigned' && !hasBed) return false;
      if (bedStatus === 'unassigned' && hasBed) return false;
      if (boardingStatus && String(student.boarding_status || '') !== boardingStatus) return false;
      if (rollCallStatus && String(student.roll_call_status_today || '') !== rollCallStatus) return false;
      return true;
    });
  },

  render() {
    const students = this.filteredStudents();
    const body = this.byId('studentsTableBody');
    if (!body) return;
    const empty = this.byId('studentsEmpty');
    empty?.classList.toggle('d-none', students.length > 0);
    if (!students.length) {
      body.innerHTML = '<tr><td colspan="13" class="text-center py-4 text-muted">No boarding students match these filters.</td></tr>';
      this.renderStats(students);
      this.populateReferenceFilters();
      return;
    }

    body.innerHTML = students.map((student) => {
      const id = Number(student.student_id) || 0;
      const rollCall = String(student.roll_call_status_today || 'unknown');
      const exeat = String(student.exeat_status || 'none');
      const hasAlert = student.has_special_alert === true || Number(student.has_special_alert) === 1;
      return `<tr>
        <td><input class="form-check-input boarding-student-select" type="checkbox" value="${id}" aria-label="Select ${this.esc(student.full_name)}"></td>
        <td class="small">${this.esc(student.admission_no || '—')}</td>
        <td class="small fw-semibold">${this.esc(student.full_name || '—')}</td>
        <td class="small">${this.esc(student.class_name || '—')}</td>
        <td class="small">${this.esc(student.stream_name || '—')}</td>
        <td class="small text-capitalize">${this.esc(student.gender || '—')}</td>
        <td class="small">${this.esc(student.dormitory_name || 'Unassigned')}</td>
        <td class="small">${this.esc(student.bed_number || '—')}</td>
        <td><span class="badge bg-success">${this.esc(student.boarding_status || 'active')}</span></td>
        <td><span class="badge bg-${rollCall === 'present' ? 'success' : rollCall === 'absent' ? 'danger' : 'secondary'}">${this.esc(rollCall)}</span></td>
        <td><span class="badge bg-${exeat === 'approved' ? 'warning text-dark' : 'secondary'}">${this.esc(exeat === 'none' ? '—' : exeat)}</span></td>
        <td>${hasAlert ? `<span class="badge bg-warning text-dark" title="${this.esc(student.special_alert_summary || 'Special boarding alert')}">Alert</span>` : '—'}</td>
        <td><div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-primary" data-student-action="profile" data-student-id="${id}" title="View boarding profile"><i class="bi bi-eye"></i></button>
          <button type="button" class="btn btn-outline-success" data-student-action="assign" data-student-id="${id}" title="Assign dormitory"><i class="bi bi-house"></i></button>
          <button type="button" class="btn btn-outline-info" data-student-action="note" data-student-id="${id}" title="Add boarding note"><i class="bi bi-journal-plus"></i></button>
        </div></td>
      </tr>`;
    }).join('');
    this.renderStats(students);
    this.populateReferenceFilters();
  },

  renderStats(students) {
    const set = (id, count) => { const element = this.byId(id); if (element) element.textContent = String(count); };
    set('totalBoarders', students.length);
    set('boysBoarders', students.filter((student) => String(student.gender).toLowerCase() === 'male').length);
    set('girlsBoarders', students.filter((student) => String(student.gender).toLowerCase() === 'female').length);
    set('onExeatCount', students.filter((student) => student.exeat_status && student.exeat_status !== 'none').length);
    set('absentCount', students.filter((student) => ['absent', 'late', 'unknown'].includes(String(student.roll_call_status_today))).length);
    set('specialAlertsCount', students.filter((student) => student.has_special_alert === true || Number(student.has_special_alert) === 1).length);
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    const rerender = () => this.render();
    ['classFilter', 'streamFilter', 'genderFilter', 'bedStatusFilter', 'boardingStatusFilter', 'rollCallStatusFilter']
      .forEach((id) => this.byId(id)?.addEventListener('change', rerender));
    ['academicYearFilter', 'dormitoryFilter'].forEach((id) => this.byId(id)?.addEventListener('change', () => this.load()));
    let searchTimer;
    this.byId('searchBox')?.addEventListener('input', () => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => this.load(), 250);
    });
    this.byId('resetFiltersBtn')?.addEventListener('click', () => {
      ['classFilter', 'streamFilter', 'genderFilter', 'dormitoryFilter', 'bedStatusFilter', 'boardingStatusFilter', 'rollCallStatusFilter', 'searchBox']
        .forEach((id) => { const element = this.byId(id); if (element) element.value = ''; });
      this.load();
    });
    this.byId('refreshBtn')?.addEventListener('click', () => this.load());
    this.byId('selectAll')?.addEventListener('change', (event) => {
      this.byId('studentsTableBody')?.querySelectorAll('.boarding-student-select').forEach((box) => { box.checked = event.target.checked; });
    });
    this.byId('studentsTableBody')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-student-action]');
      if (!button) return;
      const student = this.state.students.find((row) => Number(row.student_id) === Number(button.dataset.studentId));
      if (!student) return;
      if (button.dataset.studentAction === 'profile') this.openProfile(student);
      if (button.dataset.studentAction === 'assign') this.openAssign(student);
      if (button.dataset.studentAction === 'note') this.openNote(student);
    });
    this.byId('assignDormBtn')?.addEventListener('click', () => this.openAssignForSelection());
    this.byId('assignDormModalBtn')?.addEventListener('click', () => this.openAssignForSelection());
    this.byId('addBoardingNoteBtn')?.addEventListener('click', () => this.openNoteForSelection());
    this.byId('createExeatBtn')?.addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(this.byId('exeatModal')).show());
    this.byId('rollCallBtn')?.addEventListener('click', () => this.openRollCall());
    this.byId('printBoardingListBtn')?.addEventListener('click', () => window.print());
    this.byId('exportBoardingSheetBtn')?.addEventListener('click', () => this.exportCSV());
    this.byId('saveAssignDormBtn')?.addEventListener('click', () => this.saveAssignment());
    this.byId('saveRollCallBtn')?.addEventListener('click', () => this.saveRollCall());
    this.byId('saveExeatBtn')?.addEventListener('click', () => this.saveExeat());
    this.byId('saveBoardingNoteBtn')?.addEventListener('click', () => this.saveNote());
  },

  selectedStudents() {
    const selected = new Set(Array.from(this.byId('studentsTableBody')?.querySelectorAll('.boarding-student-select:checked') || [])
      .map((box) => Number(box.value)));
    return this.filteredStudents().filter((student) => selected.has(Number(student.student_id)));
  },

  openAssignForSelection() {
    const [student] = this.selectedStudents();
    if (!student) return this.notify('Select a student first.', 'warning');
    this.openAssign(student);
  },

  openAssign(student) {
    this.state.selectedStudentId = Number(student.student_id);
    this.byId('assignStudentId').value = String(student.student_id);
    this.byId('assignStudentName').value = student.full_name || '';
    this.byId('assignDate').value = new Date().toISOString().slice(0, 10);
    bootstrap.Modal.getOrCreateInstance(this.byId('assignDormModal')).show();
  },

  openNoteForSelection() {
    const [student] = this.selectedStudents();
    if (!student) return this.notify('Select a student first.', 'warning');
    this.openNote(student);
  },

  openNote(student) {
    this.state.selectedStudentId = Number(student.student_id);
    const name = this.byId('boardingNoteStudent');
    if (name) name.value = String(student.student_id);
    bootstrap.Modal.getOrCreateInstance(this.byId('addBoardingNoteModal')).show();
  },

  openProfile(student) {
    this.byId('modalSubtitle').textContent = `${student.full_name || ''} · ${student.admission_no || ''}`;
    this.byId('modalBoardingContent').innerHTML = `<dl class="row mb-0">
      <dt class="col-sm-4">Class / stream</dt><dd class="col-sm-8">${this.esc(student.class_name || '—')} / ${this.esc(student.stream_name || '—')}</dd>
      <dt class="col-sm-4">Dormitory / bed</dt><dd class="col-sm-8">${this.esc(student.dormitory_name || 'Unassigned')} / ${this.esc(student.bed_number || '—')}</dd>
      <dt class="col-sm-4">Roll call</dt><dd class="col-sm-8">${this.esc(student.roll_call_status_today || 'unknown')}</dd>
      <dt class="col-sm-4">Exeat</dt><dd class="col-sm-8">${this.esc(student.exeat_status || 'none')}</dd>
      <dt class="col-sm-4">Alert</dt><dd class="col-sm-8">${this.esc(student.special_alert_summary || (student.has_special_alert ? 'Special alert recorded' : 'None'))}</dd>
    </dl>`;
    bootstrap.Modal.getOrCreateInstance(this.byId('boardingProfileModal')).show();
  },

  openRollCall() {
    this.byId('rollCallDate').value = new Date().toISOString().slice(0, 10);
    const students = this.filteredStudents();
    const list = this.byId('rollCallStudentsList');
    list.innerHTML = students.length ? students.map((student) => `<div class="row align-items-center border-bottom py-2" data-roll-student="${Number(student.student_id)}">
      <div class="col-md-5">${this.esc(student.full_name)} <span class="text-muted small">${this.esc(student.admission_no)}</span></div>
      <div class="col-md-3">${this.esc(student.dormitory_name || 'Unassigned')}</div>
      <div class="col-md-4"><select class="form-select form-select-sm roll-status"><option value="present">Present</option><option value="absent">Absent</option><option value="permission">Permission</option><option value="sick_bay">Sick bay</option><option value="unknown">Unknown</option></select></div>
    </div>`).join('') : '<p class="text-muted">No students are available for roll call.</p>';
    bootstrap.Modal.getOrCreateInstance(this.byId('rollCallModal')).show();
  },

  async saveAssignment() {
    const payload = {
      student_id: Number(this.byId('assignStudentId').value),
      dormitory_id: Number(this.byId('assignDormitory').value),
      bed_number: this.byId('assignBed').value.trim() || null,
      allocation_date: this.byId('assignDate').value,
      notes: this.byId('assignNotes').value.trim() || null,
    };
    try {
      await this.API('POST', '/api/students/boarding-assign-dorm', payload);
      bootstrap.Modal.getInstance(this.byId('assignDormModal'))?.hide();
      this.notify('Dormitory assignment saved.', 'success');
      await this.load();
    } catch (error) { this.notify(error.message || 'Could not save dormitory assignment.', 'danger'); }
  },

  async saveRollCall() {
    const records = Array.from(this.byId('rollCallStudentsList').querySelectorAll('[data-roll-student]')).map((row) => ({
      student_id: Number(row.dataset.rollStudent), status: row.querySelector('.roll-status')?.value || 'present',
    }));
    try {
      await this.API('POST', '/api/boarding/roll-call', { date: this.byId('rollCallDate').value, records });
      bootstrap.Modal.getInstance(this.byId('rollCallModal'))?.hide();
      this.notify('Roll call saved.', 'success');
      await this.load();
    } catch (error) { this.notify(error.message || 'Could not save roll call.', 'danger'); }
  },

  async saveExeat() {
    const start = this.byId('exeatLeaveAt').value;
    const end = this.byId('exeatExpectedReturn').value;
    const payload = {
      student_id: Number(this.byId('exeatStudent').value),
      permission_type_id: 1,
      start_date: start ? start.slice(0, 10) : '',
      end_date: end ? end.slice(0, 10) : '',
      reason: [this.byId('exeatType').selectedOptions[0]?.textContent, this.byId('exeatDestination').value.trim(), this.byId('exeatReason').value.trim()].filter(Boolean).join(' — '),
    };
    try {
      await this.API('POST', '/api/boarding/exeats', payload);
      bootstrap.Modal.getInstance(this.byId('exeatModal'))?.hide();
      this.notify('Exeat request created.', 'success');
      await this.load();
    } catch (error) { this.notify(error.message || 'Could not create the exeat request.', 'danger'); }
  },

  async saveNote() {
    const payload = {
      student_id: Number(this.byId('boardingNoteStudent')?.value || this.state.selectedStudentId || 0),
      note_type: this.byId('boardingNoteType').value,
      note: this.byId('boardingNoteContent').value.trim(),
      visibility: this.byId('boardingNoteVisibility').value,
      priority: this.byId('boardingNotePriority').value,
    };
    try {
      await this.API('POST', '/api/students/boarding-note', payload);
      bootstrap.Modal.getInstance(this.byId('addBoardingNoteModal'))?.hide();
      this.notify('Boarding note saved.', 'success');
      this.byId('addBoardingNoteForm')?.reset();
      await this.load();
    } catch (error) { this.notify(error.message || 'Could not save boarding note.', 'danger'); }
  },

  exportCSV() {
    const rows = this.filteredStudents();
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Admission No', 'Student', 'Class', 'Stream', 'Gender', 'Dormitory', 'Bed', 'Boarding Status', 'Roll Call', 'Exeat', 'Alert'],
      ...rows.map((student) => [student.admission_no, student.full_name, student.class_name, student.stream_name, student.gender, student.dormitory_name, student.bed_number, student.boarding_status, student.roll_call_status_today, student.exeat_status, student.special_alert_summary])]
      .map((row) => row.map(cell).join(',')).join('\r\n');
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('boarding')) return this.notify('You do not have permission to export boarding data.', 'warning');
    window.KingswayFileLifecycle?.exportText?.(csv, 'boarding_students.csv', 'text/csv;charset=utf-8;');
  },

  notify(message, type = 'info') { if (window.showNotification) window.showNotification(message, type); else if (window.showToast) window.showToast(message, type); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.bindEvents();
    await this.loadReferencesComposed();
    try { await this.loadReferences(); }
    catch (error) { console.warn('Boarding filter references unavailable:', error); }
    await this.load();
    window.APIRealtime?.register?.('boarding_students', {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.loadReferences(); await this.load(); },
    }, ['boarding', 'attendance']);
  },
};

window.boardingStudentsController = boardingStudentsController;
document.addEventListener('DOMContentLoaded', () => boardingStudentsController.init().catch((error) => {
  console.error('Boarding Students initialization failed:', error);
  const loading = document.getElementById('studentsLoading');
  const errorBox = document.getElementById('studentsError');
  loading?.classList.add('d-none');
  if (errorBox) {
    errorBox.textContent = error?.message || 'Boarding students could not be initialized.';
    errorBox.classList.remove('d-none');
  }
}));
