/** Admissions reporting backed by the permission-scoped admission queue contract. */
const enrollmentReportsController = {
  applications: [], charts: {},
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); },
  payload(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  date(row) { return String(row.created_at || row.submitted_at || '').slice(0, 10); },
  filtered() {
    const from = document.getElementById('filterDateFrom')?.value || '';
    const to = document.getElementById('filterDateTo')?.value || '';
    return this.applications.filter((row) => {
      const date = this.date(row);
      return (!from || date >= from) && (!to || date <= to);
    });
  },
  async load() {
    const body = document.getElementById('reportsTableBody');
    if (body) body.innerHTML = '<tr><td colspan="7" class="text-center py-4">Loading admissions data…</td></tr>';
    const error = document.getElementById('enrollmentReportError'); if (error) error.classList.add('d-none');
    try {
      const response = await this.API('GET', '/api/admission/queues');
      const payload = this.payload(response);
      const queues = payload.queues || {};
      if (!Array.isArray(queues.all_applications)) {
        throw new Error('Your account does not have access to the admissions reporting queue.');
      }
      this.applications = queues.all_applications;
      this.render();
    } catch (exception) {
      if (body) body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Admissions data could not be loaded.</td></tr>';
      if (error) { error.textContent = exception?.message || 'Admissions data could not be loaded.'; error.classList.remove('d-none'); }
    }
  },
  render() {
    const rows = this.filtered();
    const statuses = (needle) => rows.filter((row) => String(row.status || '').toLowerCase().includes(needle)).length;
    const text = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    text('statTotalApplications', rows.length);
    text('statApproved', statuses('approved') + statuses('admitted') + statuses('enrolled'));
    text('statWaitlisted', statuses('waitlist'));
    text('statEnrolled', statuses('enrolled'));
    const body = document.getElementById('reportsTableBody');
    if (body) body.innerHTML = rows.length ? rows.map((row) => `<tr>
      <td>${this.esc(row.application_no || '—')}</td><td>${this.esc(row.applicant_name || '—')}</td>
      <td>${this.esc(row.grade_applying_for || '—')}</td><td>${this.esc(row.gender || '—')}</td>
      <td>${this.esc(row.status || '—')}</td><td>${this.esc(this.date(row) || '—')}</td>
      <td>${this.esc(String(row.current_stage || '—').replace(/_/g, ' '))}</td>
    </tr>`).join('') : '<tr><td colspan="7" class="text-center py-4 text-muted">No applications match the selected dates.</td></tr>';
    this.drawCharts(rows);
  },
  drawCharts(rows) {
    if (!window.Chart) return;
    const make = (id, type, labels, values, colors) => {
      const canvas = document.getElementById(id); if (!canvas) return;
      this.charts[id]?.destroy();
      this.charts[id] = new Chart(canvas, { type, data: { labels, datasets: [{ data: values, backgroundColor: colors || ['#0d6efd','#198754','#ffc107','#dc3545','#6c757d'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: type === 'doughnut' || type === 'pie' } } } });
    };
    const countBy = (key, normalize = (v) => v || 'Unknown') => {
      const values = new Map(); rows.forEach((row) => { const k = normalize(row[key]); values.set(k, (values.get(k) || 0) + 1); });
      return { labels: [...values.keys()], values: [...values.values()] };
    };
    const status = countBy('status'); make('statusChart', 'doughnut', status.labels, status.values);
    const classes = countBy('grade_applying_for'); make('classChart', 'bar', classes.labels, classes.values);
    const genders = countBy('gender'); make('genderChart', 'doughnut', genders.labels, genders.values);
    const months = countBy('created_at', (value) => String(value || '').slice(0, 7) || 'Unknown');
    make('trendChart', 'line', months.labels, months.values);
  },
  refreshData() { return this.load(); },
  exportReport() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('admissions')) return window.showNotification?.('You do not have permission to export admissions data.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Application No','Applicant','Grade','Gender','Status','Submitted','Stage'], ...this.filtered().map((row) => [row.application_no,row.applicant_name,row.grade_applying_for,row.gender,row.status,this.date(row),row.current_stage])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'admissions-report.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    ['filterDateFrom','filterDateTo'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.render()));
    document.getElementById('filterAcademicYear')?.closest('.col-md-3')?.classList.add('d-none');
    document.getElementById('filterTerm')?.closest('.col-md-3')?.classList.add('d-none');
    document.getElementById('filterReportType')?.addEventListener('change', () => this.render());
    document.getElementById('printEnrollmentReport')?.addEventListener('click', () => {
      if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('admissions')) window.print();
    });
    window.APIRealtime?.register?.('enrollment_reports', { refresh: () => this.load() }, ['admissions']);
    return this.load();
  }
};
window.enrollmentReportsController = enrollmentReportsController;
document.addEventListener('DOMContentLoaded', () => enrollmentReportsController.init());
