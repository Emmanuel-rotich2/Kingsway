/** Subject teacher performance dashboard; result rows are server scoped to teaching assignments. */
const studentSubjectPerformanceController = {
  state: { results: [], request: 0 },
  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  rows(response) { return Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []); },
  async loadFilters() {
    const [yearsResponse, termsResponse, subjectsResponse] = await Promise.all([
      this.api('GET', '/api/academic/years-list'),
      this.api('GET', '/api/academic/terms-list'),
      this.api('GET', '/api/academic/subjects-list', null, { subject_teacher_only: true }),
    ]);
    const years = this.rows(yearsResponse); const terms = this.rows(termsResponse); const subjects = this.rows(subjectsResponse);
    const fill = (id, items, label, current) => {
      const select = document.getElementById(id); if (!select) return;
      const first = select.options[0]?.outerHTML || '<option value="">All</option>';
      select.innerHTML = first + items.map((item) => `<option value="${Number(item.id)}" ${current(item) ? 'selected' : ''}>${this.esc(label(item))}</option>`).join('');
    };
    fill('yearFilter', years, (x) => x.year_name || x.year_code || x.name, (x) => Boolean(x.is_current));
    fill('termFilter', terms, (x) => `${x.name || x.term_name || 'Term'}${x.year_name ? ` (${x.year_name})` : ''}`, (x) => x.status === 'current' || x.is_current);
    fill('subjectFilter', subjects, (x) => x.name || x.subject_name, () => false);
  },
  async load() {
    const request = ++this.state.request;
    const loading = document.getElementById('performanceLoading'); const content = document.getElementById('performanceContent');
    if (loading) loading.style.display = ''; if (content) content.style.display = 'none';
    const params = {};
    const year = document.getElementById('yearFilter')?.value; const term = document.getElementById('termFilter')?.value; const subject = document.getElementById('subjectFilter')?.value;
    if (year) params.year_id = year; if (term) params.term_id = term; if (subject) params.subject_id = subject;
    params.subject_teacher_only = true;
    try {
      const response = await this.api('GET', '/api/academic/results', null, params);
      if (request !== this.state.request) return;
      if (response?.success === false || response?.error) throw new Error(response.message || response.error || 'Could not load subject performance.');
      this.state.results = this.rows(response); this.render();
      if (loading) loading.style.display = 'none'; if (content) content.style.display = '';
    } catch (error) {
      if (request !== this.state.request) return;
      const body = document.getElementById('performanceTableBody');
      if (body) body.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${this.esc(error?.message || 'Could not load performance data. Check your teaching assignment and try again.')}</td></tr>`;
      if (loading) loading.style.display = 'none'; if (content) content.style.display = '';
    }
  },
  summarize() {
    const groups = new Map();
    this.state.results.forEach((row) => {
      const key = `${row.admission_no || row.student_name}|${row.class_name || ''}`;
      if (!groups.has(key)) groups.set(key, { student_name: row.student_name, admission_no: row.admission_no, class_name: row.class_name, marks: [], assessments: [] });
      const group = groups.get(key); const mark = Number(row.marks);
      if (Number.isFinite(mark)) { group.marks.push(mark); group.assessments.push({ title: row.assessment_title, mark, date: row.assessment_date }); }
    });
    return [...groups.values()].map((group) => {
      const avg = group.marks.length ? group.marks.reduce((sum, value) => sum + value, 0) / group.marks.length : null;
      const best = [...group.assessments].sort((a, b) => b.mark - a.mark)[0]; const weakest = [...group.assessments].sort((a, b) => a.mark - b.mark)[0];
      return { ...group, average: avg, best, weakest };
    });
  },
  render() {
    const students = this.summarize(); const body = document.getElementById('performanceTableBody');
    if (body) body.innerHTML = students.length ? students.map((student) => `<tr>
      <td>${this.esc(student.student_name || '—')}</td><td>${this.esc(student.admission_no || '—')}</td><td>${this.esc(student.class_name || '—')}</td>
      <td class="text-center">${student.average == null ? '—' : `${student.average.toFixed(1)}%`}</td>
      <td>${this.esc(student.best?.title || '—')}${student.best ? ` <span class="text-muted">(${student.best.mark.toFixed(1)}%)</span>` : ''}</td>
      <td>${this.esc(student.weakest?.title || '—')}${student.weakest ? ` <span class="text-muted">(${student.weakest.mark.toFixed(1)}%)</span>` : ''}</td>
      <td class="text-muted">—</td><td class="text-muted">—</td>
    </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No results are available for your assigned subjects and selected filters.</td></tr>';
    const values = students.map((x) => x.average).filter((x) => x != null);
    const cohortAverage = values.length ? values.reduce((a, b) => a + b, 0) / values.length : null;
    this.text('totalStudents', students.length);
    this.text('aboveAverage', cohortAverage == null ? 0 : values.filter((x) => x > cohortAverage).length);
    this.text('subjectAverage', cohortAverage == null ? '—' : `${cohortAverage.toFixed(1)}%`);
  },
  text(id, value) { const el = document.getElementById(id); if (el) el.textContent = String(value); },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('results')) return window.showNotification?.('You do not have permission to export results.', 'error');
    const quote = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [['Student', 'Admission No', 'Class', 'Subject', 'Assessment', 'Date', 'Score %'], ...this.state.results.map((r) => [r.student_name, r.admission_no, r.class_name, r.subject_name, r.assessment_title, r.assessment_date, r.marks])];
    window.KingswayFileLifecycle?.exportText?.(lines.map((line) => line.map(quote).join(',')).join('\r\n'), 'subject-performance.csv', 'text/csv;charset=utf-8');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('results')) return window.showNotification?.('You do not have permission to print results.', 'error'); window.print(); },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    document.getElementById('loadPerformanceBtn')?.remove();
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('printBtn')?.addEventListener('click', () => this.print());
    ['yearFilter', 'termFilter', 'subjectFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    try { await this.loadFilters(); } catch (error) { console.error('Could not load subject performance filters:', error); }
    await this.load();
    window.APIRealtime?.register?.('student_subject_performance', { refresh: () => this.load() }, ['students', 'academic']);
  },
};
window.studentSubjectPerformanceController = studentSubjectPerformanceController;
document.addEventListener('DOMContentLoaded', () => studentSubjectPerformanceController.init().catch((error) => console.error('Subject performance page failed to initialize:', error)));
