/** Teacher-scoped exam schedule viewer using the canonical exam-schedules API. */
const subjectExamScheduleController = {
  state: { years: [], terms: [], subjects: [], exams: [], summary: {} },
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); },
  payload(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  list(response) { const value = this.payload(response); return Array.isArray(value) ? value : (Array.isArray(value.data) ? value.data : []); },
  async init() {
    await window.AuthContext?.ready?.();
    document.getElementById('yearFilter')?.addEventListener('change', () => this.loadTerms().then(() => this.loadExams()));
    document.getElementById('termFilter')?.addEventListener('change', () => this.loadExams());
    document.getElementById('subjectFilter')?.addEventListener('change', () => this.loadExams());
    document.getElementById('refreshBtn')?.addEventListener('click', () => this.loadExams());
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());
    const table = document.querySelector('#scheduleTable tbody');
    if (table) table.id = 'scheduleTableBody';
    document.getElementById('schedulePrint')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('academic')) window.print(); });
    await this.loadReferences();
    await this.loadExams();
  },
  async loadReferences() {
    this.setLoading(true);
    const error = document.getElementById('scheduleError'); error?.classList.add('d-none');
    try {
      const [yearsResponse, subjectsResponse] = await Promise.all([
        this.API('GET', '/api/academic/years/list'), this.API('GET', '/api/academic/learning-areas/list')
      ]);
      this.state.years = this.list(yearsResponse); this.state.subjects = this.list(subjectsResponse);
      const year = document.getElementById('yearFilter');
      const subject = document.getElementById('subjectFilter');
      if (year) {
        year.innerHTML = '<option value="">All Years</option>' + this.state.years.map((row) => `<option value="${Number(row.id)}">${this.esc(row.year_name || row.year_code)}</option>`).join('');
        const current = this.state.years.find((row) => Number(row.is_current) === 1 || row.is_current === true);
        const contextYear = window.AcademicContext?.getAcademicYearId?.();
        year.value = String(contextYear || current?.id || '');
      }
      if (subject) subject.innerHTML = '<option value="">All Subjects</option>' + this.state.subjects.map((row) => `<option value="${Number(row.id)}">${this.esc(row.name)}</option>`).join('');
      await this.loadTerms();
    } catch (exception) {
      this.showError(exception?.message || 'Could not load academic filters.');
    }
  },
  async loadTerms() {
    const yearId = document.getElementById('yearFilter')?.value || '';
    const params = yearId ? { academic_year_id: yearId, year_id: yearId } : {};
    const response = await this.API('GET', '/api/academic/terms/list', null, params);
    this.state.terms = this.list(response);
    const term = document.getElementById('termFilter');
    if (!term) return;
    term.innerHTML = '<option value="">All Terms</option>' + this.state.terms.map((row) => `<option value="${Number(row.id)}">${this.esc(row.name || row.term_name)}</option>`).join('');
    const current = this.state.terms.find((row) => String(row.status || '').toLowerCase() === 'current');
    if (current) term.value = String(current.id);
  },
  async loadExams() {
    this.setLoading(true);
    const error = document.getElementById('scheduleError'); error?.classList.add('d-none');
    try {
      const params = { limit: 500 };
      const year = document.getElementById('yearFilter')?.value || '';
      const term = document.getElementById('termFilter')?.value || '';
      const subject = document.getElementById('subjectFilter')?.value || '';
      if (year) params.academic_year_id = year;
      if (term) params.term_id = term;
      if (subject) params.subject_id = subject;
      const response = await this.API('GET', '/api/academic/exam-schedules', null, params);
      const payload = this.payload(response);
      this.state.exams = Array.isArray(payload.exams) ? payload.exams : [];
      this.state.summary = payload.summary || {};
      this.render();
    } catch (exception) {
      this.renderRows([]);
      this.showError(exception?.message || 'Exam schedules could not be loaded.');
    } finally { this.setLoading(false); }
  },
  setLoading(loading) {
    const spinner = document.getElementById('scheduleLoading'); if (spinner) spinner.classList.toggle('d-none', !loading);
    const content = document.getElementById('scheduleContent'); if (content) content.style.display = loading ? 'none' : '';
  },
  showError(message) {
    const box = document.getElementById('scheduleError');
    if (box) { box.textContent = message; box.classList.remove('d-none'); }
    else window.showNotification?.(message, 'error');
  },
  render() {
    const summary = this.state.summary;
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    set('totalExams', summary.total ?? this.state.exams.length); set('upcomingExams', summary.upcoming ?? '—'); set('completedExams', summary.completed ?? '—');
    this.renderRows(this.state.exams);
  },
  renderRows(rows) {
    const body = document.getElementById('scheduleTableBody'); if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => `<tr>
      <td>${this.esc(row.exam_name || row.exam_period_title || row.assessment_title || '—')}</td>
      <td>${this.esc(row.subject_name || '—')}</td><td>${this.esc([row.class_name,row.stream_name].filter(Boolean).join(' / ') || '—')}</td>
      <td>${this.esc(row.exam_type || row.assessment_type_name || '—')}</td><td>${this.esc(row.exam_date || '—')}</td>
      <td>${this.esc(row.start_time || '—')}</td><td>${row.duration == null ? '—' : `${Number(row.duration)} min`}</td>
      <td><span class="badge bg-${row.status === 'completed' ? 'success' : row.status === 'cancelled' ? 'secondary' : 'primary'}">${this.esc(row.status || '—')}</span></td>
    </tr>`).join('') : '<tr><td colspan="8" class="text-center py-4 text-muted">No exam schedules found for the selected scope.</td></tr>';
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) return window.showNotification?.('You do not have permission to export exam schedules.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Exam','Subject','Class','Stream','Type','Date','Start Time','Duration (minutes)','Status'], ...this.state.exams.map((row) => [row.exam_name,row.subject_name,row.class_name,row.stream_name,row.exam_type,row.exam_date,row.start_time,row.duration,row.status])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'subject-exam-schedule.csv', 'text/csv;charset=utf-8;');
  }
};
window.subjectExamScheduleController = subjectExamScheduleController;
document.addEventListener('DOMContentLoaded', () => subjectExamScheduleController.init());
