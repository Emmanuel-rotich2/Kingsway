/** Subject report viewer backed by the academic reports endpoint. */
const generateSubjectReportController = {
  state: { years: [], terms: [], classes: [], subjects: [], columns: [], rows: [], summary: {} },
  API: (method, endpoint, data = null, params = null) => window.callAPI(endpoint, method, data, params),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  rows(response) { const data = response?.data?.data ?? response?.data ?? response ?? []; return Array.isArray(data) ? data : Array.isArray(data?.rows) ? data.rows : []; },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const [yearsResponse, classesResponse, subjectsResponse] = await Promise.all([
      this.API('GET', '/api/academic/years/list'),
      this.API('GET', '/api/academic/classes-list'),
      this.API('GET', '/api/academic/learning-areas/list'),
    ]);
    this.state.years = this.rows(yearsResponse); this.state.classes = this.rows(classesResponse); this.state.subjects = this.rows(subjectsResponse);
    this.fill('yearFilter', this.state.years, 'All Years', (item) => item.year_name || item.name || item.year_code);
    this.fill('classFilter', this.state.classes, 'All Classes', (item) => item.name || item.class_name);
    this.fill('subjectFilter', this.state.subjects, 'All Learning Areas', (item) => item.name || item.learning_area || item.subject_name);
    const currentYear = this.state.years.find((item) => item.is_current || item.status === 'current');
    if (currentYear) document.getElementById('yearFilter').value = String(currentYear.id);
    await this.loadTerms();
    document.getElementById('reportType')?.addEventListener('change', () => this.load());
    document.getElementById('yearFilter')?.addEventListener('change', async () => { await this.loadTerms(); await this.load(); });
    ['termFilter','classFilter','subjectFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    document.getElementById('refreshBtn')?.addEventListener('click', () => this.load());
    document.getElementById('printBtn')?.addEventListener('click', () => this.print());
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());
    await this.load();
    window.APIRealtime?.register?.('generate_subject_report', { refresh: () => this.load() }, ['reports','academic']);
  },
  fill(id, items, label, format) {
    const select = document.getElementById(id); if (!select) return;
    select.innerHTML = `<option value="">${this.esc(label)}</option>` + items.map((item) => `<option value="${Number(item.id)}">${this.esc(format(item))}</option>`).join('');
  },
  async loadTerms() {
    const yearId = document.getElementById('yearFilter')?.value || '';
    try {
      const response = await this.API('GET', '/api/academic/terms', null, yearId ? { academic_year_id: yearId } : {});
      this.state.terms = this.rows(response);
      this.fill('termFilter', this.state.terms, 'All Terms', (item) => `${item.name || item.term_name || 'Term'} · ${item.year_name || ''}`);
      const current = this.state.terms.find((item) => item.is_current || item.status === 'current');
      if (current) document.getElementById('termFilter').value = String(current.id);
    } catch (error) { this.state.terms = []; this.fill('termFilter', [], 'All Terms', () => ''); this.showError(error.message || 'Terms could not be loaded.'); }
  },
  async load() {
    this.setLoading(true); this.hideError();
    const params = {
      report_type: document.getElementById('reportType')?.value || 'performance',
      year_id: document.getElementById('yearFilter')?.value || '',
      term_id: document.getElementById('termFilter')?.value || '',
      class_id: document.getElementById('classFilter')?.value || '',
      subject_id: document.getElementById('subjectFilter')?.value || '',
      subject_teacher_only: '1',
    };
    Object.keys(params).forEach((key) => { if (!params[key]) delete params[key]; });
    try {
      const response = await this.API('GET', '/api/academic/reports', null, params);
      const data = response?.data?.data ?? response?.data ?? response ?? {};
      this.state.columns = Array.isArray(data.columns) ? data.columns : []; this.state.rows = Array.isArray(data.rows) ? data.rows : []; this.state.summary = data.summary || {};
      this.render();
    } catch (error) { this.state.columns = []; this.state.rows = []; this.state.summary = {}; this.render(); this.showError(error.message || 'The subject report could not be loaded.'); }
    finally { this.setLoading(false); }
  },
  setLoading(loading) { document.getElementById('reportLoading')?.classList.toggle('d-none', !loading); const content = document.getElementById('reportContent'); if (content) content.style.display = loading ? 'none' : ''; },
  showError(message) { const error = document.getElementById('reportError'); if (error) { error.textContent = message; error.classList.remove('d-none'); } },
  hideError() { document.getElementById('reportError')?.classList.add('d-none'); },
  render() {
    const head = document.getElementById('reportsTableHead'); const body = document.getElementById('reportsTableBody'); const summary = document.getElementById('reportSummary');
    if (head) head.innerHTML = this.state.columns.map((column) => `<th scope="col">${this.esc(column.label || column.key)}</th>`).join('');
    if (body) body.innerHTML = this.state.rows.length ? this.state.rows.map((row) => `<tr>${this.state.columns.map((column) => `<td>${this.esc(row[column.key] ?? '—')}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${Math.max(1,this.state.columns.length)}" class="text-center text-muted py-4">No report data matches these filters.</td></tr>`;
    if (summary) summary.innerHTML = Object.entries(this.state.summary).map(([label,value]) => `<span class="badge text-bg-light border">${this.esc(label)}: ${Number(value || 0).toLocaleString()}</span>`).join(' ');
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('reports')) return window.showNotification?.('You do not have permission to export reports.', 'error');
    if (!this.state.columns.length || !this.state.rows.length) return window.showNotification?.('There is no report data to export.', 'info');
    const quote = (value) => `"${String(value ?? '').replaceAll('"','""')}"`;
    const csv = [this.state.columns.map((column) => column.label), ...this.state.rows.map((row) => this.state.columns.map((column) => row[column.key]))].map((row) => row.map(quote).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'subject-report.csv', 'text/csv;charset=utf-8;');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('reports')) return window.showNotification?.('You do not have permission to print reports.', 'error'); window.print(); },
};
window.generateSubjectReportController = generateSubjectReportController;
document.addEventListener('DOMContentLoaded', () => generateSubjectReportController.init().catch((error) => { generateSubjectReportController.setLoading(false); generateSubjectReportController.showError(error.message || 'Subject report page setup failed.'); }));
