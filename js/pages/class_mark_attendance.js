const ClassMarkAttendanceController = {
  students: [],
  classes: [],
  async init() {
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.isAuthenticated()) return;
    const date = document.getElementById('classAttendanceDate');
    if (date) date.value = this.localDate();
    document.getElementById('classAttendanceStream')?.addEventListener('change', () => this.loadSessions());
    document.getElementById('classAttendanceDate')?.addEventListener('change', () => this.loadSessions());
    document.getElementById('classAttendanceSession')?.addEventListener('change', () => this.loadStudents());
    document.getElementById('saveClassAttendance')?.addEventListener('click', () => this.save());
    await this.loadClasses();
    this.bindRealtime();
  },

  /**
   * Realtime collaboration: changing any learner's status declares this
   * register as actively marked, so a peer (or leadership viewing the same
   * register) sees the editing badge before saving over in-progress marks;
   * a peer's saved register arrives as a DATA_CHANGED refresh.
   */
  bindRealtime() {
    const presenceKey = () => `attendance_register:${document.getElementById('classAttendanceStream')?.value || 0}:${document.getElementById('classAttendanceSession')?.value || 0}:${document.getElementById('classAttendanceDate')?.value || ''}`;
    const body = document.getElementById('classAttendanceBody');
    body?.addEventListener('change', () => window.RealtimePresence?.begin(presenceKey()));
    body?.addEventListener('input', () => window.RealtimePresence?.begin(presenceKey()));
    window.addEventListener('pagehide', () => window.RealtimePresence?.end(presenceKey()), { once: true });
    window.APIRealtime?.register?.('class_mark_attendance', { refresh: () => this.loadStudents() }, ['attendance']);
  },
  unwrap(response) {
    const value = response?.data?.data || response?.data || response || [];
    if (Array.isArray(value)) return value;
    return value?.classes || value?.streams || value?.students || value?.rows || [];
  },
  async loadSessions() {
    try {
      const date = document.getElementById('classAttendanceDate')?.value;
      const stream = document.getElementById('classAttendanceStream')?.value;
      const select = document.getElementById('classAttendanceSession');
      const wrapper = select?.closest('.col-md-3');
      if (!stream) {
        select.innerHTML = '<option value="">Select your class first</option>';
        if (wrapper) wrapper.classList.add('d-none');
        return;
      }
      const response = await window.API.apiCall('/attendance/sessions', 'GET', null, { type: 'academic', stream_id: stream, date, day: new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { weekday: 'long' }) });
      const rows = this.unwrap(response);
      select.innerHTML = rows.length ? rows.map(s => `<option value="${this.esc(s.id)}">${this.esc(s.name || s.session_name || 'Class session')}</option>`).join('') : '<option value="">No applicable session</option>';
      this.stateSessions = rows;
      this.applySessionPolicy();
      if (document.getElementById('classAttendanceStream')?.value) await this.loadStudents();
    } catch (e) { this.message(e.message || 'Unable to load applicable attendance sessions.', 'danger'); }
  },
  async loadClasses() {
    try {
      let data = this.unwrap(await window.API.apiCall('/attendance/classes', 'GET'));
      // Use the canonical teacher-class learner scope as a fallback. This
      // keeps the register aligned with My Students when the legacy
      // attendance class-list permission/context returns no rows.
      if (!Array.isArray(data) || !data.length) {
        const scopedResponse = await window.API.apiCall('/students/context-list', 'GET', null, { context: 'teacher_class' });
        const scopedPayload = scopedResponse?.data?.data || scopedResponse?.data || scopedResponse || {};
        const learners = scopedPayload?.students || [];
        data = [...new Map(learners.filter(s => s.academic_year_class_stream_id).map(s => [String(s.academic_year_class_stream_id), {
          stream_id: s.academic_year_class_stream_id,
          display_name: `${s.class_name || 'Class'}${s.stream_name ? ' - ' + s.stream_name : ''}`,
          student_count: 0
        }])).values()];
        data.forEach(c => { c.student_count = learners.filter(s => String(s.academic_year_class_stream_id) === String(c.stream_id)).length; });
      }
      const select = document.getElementById('classAttendanceStream');
      this.classes = data;
      (Array.isArray(data) ? data : []).forEach(c => { const o = document.createElement('option'); o.value = c.stream_id; o.textContent = `${c.display_name || c.name} (${c.student_count || 0})`; select.appendChild(o); });
      if (data.length === 1) { select.value = data[0].stream_id; await this.loadSessions(); }
    } catch (e) { this.message(e.message || 'Unable to load your class streams.', 'danger'); }
  },
  applySessionPolicy() {
    const session = document.getElementById('classAttendanceSession');
    const wrapper = session?.closest('.col-md-3');
    const sessions = this.stateSessions || [];
    if (session && sessions.length === 1) session.value = String(sessions[0].id);
    if (wrapper) wrapper.classList.toggle('d-none', sessions.length <= 1);
  },
  async loadStudents() {
    const stream = document.getElementById('classAttendanceStream')?.value;
    const date = document.getElementById('classAttendanceDate')?.value;
    const session = document.getElementById('classAttendanceSession')?.value;
    if (!stream || !date) return this.message('Select a class stream and date.', 'warning');
    try {
      const params = new URLSearchParams({ date });
      if (session) params.set('session_id', session);
      const data = this.unwrap(await window.API.apiCall(`/attendance/students-by-class/${encodeURIComponent(stream)}?${params.toString()}`, 'GET'));
      this.students = Array.isArray(data) ? data : [];
      this.render();
    } catch (e) { this.message(e.message || 'Unable to load your learners.', 'danger'); }
  },
  render() {
    const body = document.getElementById('classAttendanceBody');
    body.innerHTML = this.students.length ? this.students.map(s => {
      const status = s.attendance_status || s.stored_status || 'not_marked';
      return `<tr data-id="${Number(s.id)}"><td>${this.esc(s.admission_no)}</td><td><strong>${this.esc([s.first_name, s.last_name].filter(Boolean).join(' '))}</strong></td><td><select class="form-select form-select-sm att-status"><option value="not_marked" ${status === 'not_marked' ? 'selected' : ''}>Not marked</option><option value="present" ${status === 'present' ? 'selected' : ''}>Present</option><option value="absent" ${status === 'absent' ? 'selected' : ''}>Absent</option><option value="late" ${status === 'late' ? 'selected' : ''}>Late</option></select></td><td><input class="form-control form-control-sm att-note" placeholder="Optional note"></td></tr>`;
    }).join('') : '<tr><td colspan="4" class="text-center text-muted py-4">No learners are assigned to this stream.</td></tr>';
    body.querySelectorAll('tr[data-id]').forEach(row => {
      const learner = this.students.find(student => String(student.id) === String(row.dataset.id));
      row.querySelector('.att-note').value = learner?.notes || '';
    });
    this.updateCounts();
    body.querySelectorAll('.att-status').forEach(e => e.addEventListener('change', () => this.updateCounts()));
  },
  updateCounts() { const statuses = [...document.querySelectorAll('.att-status')].map(e => e.value); document.getElementById('classTotal').textContent = statuses.length; document.getElementById('classPresent').textContent = statuses.filter(s => s === 'present').length; document.getElementById('classAbsent').textContent = statuses.filter(s => s === 'absent').length; document.getElementById('classPending').textContent = statuses.filter(s => s === 'not_marked').length; },
  async save() {
    const stream_id = document.getElementById('classAttendanceStream')?.value; const date = document.getElementById('classAttendanceDate')?.value; const session_id = document.getElementById('classAttendanceSession')?.value || null;
    const rows = [...document.querySelectorAll('#classAttendanceBody tr[data-id]')];
    const attendance = rows.map(row => ({ student_id: Number(row.dataset.id), status: row.querySelector('.att-status').value, note: row.querySelector('.att-note').value })).filter(record => record.status !== 'not_marked');
    const pending = rows.length - attendance.length;
    if (!stream_id || !session_id || !rows.length) return this.message('Select an applicable attendance session before saving.', 'warning');
    if (!attendance.length) return this.message('No learner has been marked. Every learner remains Not marked.', 'warning');
    try { await window.API.apiCall('/attendance/mark-bulk', 'POST', { stream_id, date, session_id, register_type: 'class', attendance }); window.RealtimePresence?.end(`attendance_register:${stream_id}:${session_id}:${date}`); await this.loadStudents(); this.message(`Attendance saved for ${attendance.length} learner${attendance.length === 1 ? '' : 's'}. ${pending ? `${pending} remain Not marked.` : 'The register is complete.'}`, pending ? 'warning' : 'success'); } catch (e) { this.message(e.message || 'Attendance could not be saved.', 'danger'); }
  },
  localDate() { const now = new Date(); const offset = now.getTimezoneOffset() * 60000; return new Date(now.getTime() - offset).toISOString().slice(0, 10); },
  message(text, type) { const el = document.getElementById('classAttendanceMessage'); el.textContent = text; el.className = `alert alert-${type}`; },
  esc(value) { const d = document.createElement('div'); d.textContent = value == null ? '' : String(value); return d.innerHTML; }
};
document.addEventListener('DOMContentLoaded', () => ClassMarkAttendanceController.init());

window.ClassMarkAttendanceController = ClassMarkAttendanceController;
