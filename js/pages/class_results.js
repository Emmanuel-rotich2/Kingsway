/** Class-teacher results view using the server-enforced class-teacher scope. */
const classResultsController = {
  state: { rows: [], request: 0, years: [], terms: [], classes: [] },
  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  data(response) { return response?.data?.data ?? response?.data ?? response; },
  list(response) { const data = this.data(response); return Array.isArray(data) ? data : []; },
  async loadFilters() {
    const [yearsResponse, termsResponse, classesResponse] = await Promise.all([
      this.api('GET', '/api/academic/years-list'), this.api('GET', '/api/academic/terms-list'), this.api('GET', '/api/academic/classes-list'),
    ]);
    this.state.years = this.list(yearsResponse); this.state.terms = this.list(termsResponse); this.state.classes = this.list(classesResponse);
    const fill = (id, items, label, current) => {
      const select = document.getElementById(id); if (!select) return;
      const first = select.options[0]?.outerHTML || '<option value="">All</option>';
      select.innerHTML = first + items.map((x) => `<option value="${Number(x.id)}" ${current(x) ? 'selected' : ''}>${this.esc(label(x))}</option>`).join('');
    };
    fill('yearFilter', this.state.years, (x) => x.year_name || x.year_code || x.name, (x) => Boolean(x.is_current));
    fill('termFilter', this.state.terms, (x) => `${x.name || x.term_name || 'Term'}${x.year_name ? ` (${x.year_name})` : ''}`, (x) => x.status === 'current');
    fill('classFilter', this.state.classes, (x) => x.name || x.class_name, () => false);
  },
  async load() {
    const request = ++this.state.request; const loading = document.getElementById('resultsLoading'); const content = document.getElementById('resultsContent');
    if (loading) loading.style.display = ''; if (content) content.style.display = 'none';
    const params = { view_mode: 'students', class_teacher_only: true, limit: 500 };
    const year = document.getElementById('yearFilter')?.value; const term = document.getElementById('termFilter')?.value; const classId = document.getElementById('classFilter')?.value;
    if (year) params.academic_year_id = year; if (term) params.term_id = term; if (classId) params.class_id = classId;
    try {
      const response = await this.api('GET', '/api/academic/performance-overview', null, params);
      if (request !== this.state.request) return;
      const payload = this.data(response);
      if (response?.success === false || response?.error) throw new Error(response.message || response.error || 'Could not load class results.');
      this.state.rows = Array.isArray(payload?.rows) ? payload.rows : [];
      this.render(); if (loading) loading.style.display = 'none'; if (content) content.style.display = '';
    } catch (error) {
      if (request !== this.state.request) return;
      const body = document.getElementById('resultsTableBody');
      if (body) body.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${this.esc(error?.message || 'Class results could not be loaded.')}</td></tr>`;
      if (loading) loading.style.display = 'none'; if (content) content.style.display = '';
      ['totalStudents', 'averageScore', 'aboveAverage'].forEach((id) => this.text(id, '—'));
    }
  },
  render() {
    const rows = this.state.rows; const body = document.getElementById('resultsTableBody');
    if (body) body.innerHTML = rows.length ? rows.map((row) => `<tr>
      <td>${this.esc(row.full_name || row.student_name || '—')}</td><td>${this.esc(row.admission_no || '—')}</td><td>${this.esc(row.class_name || '—')}</td>
      <td>${this.esc(row.term_name || 'Selected term')}</td><td class="text-center">${row.average_score == null ? '—' : `${Number(row.average_score).toFixed(1)}%`}</td>
      <td class="text-center">${this.esc(row.grade || '—')}</td><td>${this.esc(row.teacher_comment || row.remarks || '—')}</td>
    </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No results are recorded for your assigned classes and selected period.</td></tr>';
    const scores = rows.map((x) => Number(x.average_score)).filter(Number.isFinite);
    const mean = scores.length ? scores.reduce((a, b) => a + b, 0) / scores.length : null;
    this.text('totalStudents', rows.length); this.text('averageScore', mean == null ? '—' : `${mean.toFixed(1)}%`);
    this.text('aboveAverage', mean == null ? 0 : scores.filter((x) => x > mean).length);
  },
  text(id, value) { const el = document.getElementById(id); if (el) el.textContent = String(value); },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('results')) return window.showNotification?.('You do not have permission to export results.', 'error');
    const quote = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = [['Student', 'Admission No', 'Class', 'Term', 'Average %', 'Grade', 'Remarks'], ...this.state.rows.map((r) => [r.full_name || r.student_name, r.admission_no, r.class_name, r.term_name, r.average_score, r.grade, r.teacher_comment || r.remarks])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((r) => r.map(quote).join(',')).join('\r\n'), 'class-results.csv', 'text/csv;charset=utf-8');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('results')) return window.showNotification?.('You do not have permission to print results.', 'error'); window.print(); },
  init() {
    document.getElementById('loadResultsBtn')?.remove();
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV()); document.getElementById('printBtn')?.addEventListener('click', () => this.print());
    ['yearFilter', 'termFilter', 'classFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    document.getElementById('subjectFilter')?.closest('.col-md-3')?.classList.add('d-none');
    this.loadFilters().then(() => this.load()).catch((error) => {
      console.error('Class results filters failed:', error); this.load();
    });
    window.APIRealtime?.register?.('class_results', { refresh: () => this.load() }, ['academic', 'reports']);
  },
};
window.classResultsController = classResultsController;
document.addEventListener('DOMContentLoaded', () => classResultsController.init());
