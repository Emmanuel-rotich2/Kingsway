/** Health reports from the live summary and sick-bay visit APIs. */
const healthReportsController = {
  state: { visits: [], summary: {}, request: 0 },
  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  unwrap(response) { return response?.data?.data ?? response?.data ?? response; },
  async load() {
    const request = ++this.state.request;
    const body = document.getElementById('hrBody'); const error = document.getElementById('hrError');
    if (error) error.classList.add('d-none');
    if (body) body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr>';
    try {
      const [summaryResponse, visitsResponse] = await Promise.all([
        this.api('GET', '/api/health/summary'), this.api('GET', '/api/health/sick-bay'),
      ]);
      if (request !== this.state.request) return;
      this.state.summary = this.unwrap(summaryResponse) || {};
      const visits = this.unwrap(visitsResponse);
      this.state.visits = Array.isArray(visits) ? visits : [];
      this.render();
    } catch (e) {
      if (request !== this.state.request) return;
      if (body) body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Sick-bay visits could not be loaded.</td></tr>';
      if (error) { error.textContent = e?.message || 'Health report data could not be loaded. Refresh to try again.'; error.classList.remove('d-none'); }
      this.state.summary = {}; this.renderKpis();
    }
  },
  render() {
    const body = document.getElementById('hrBody'); const visits = this.state.visits;
    if (body) body.innerHTML = visits.length ? visits.map((row) => `<tr>
      <td>${this.esc(row.admission_no || '—')}</td>
      <td>${this.esc([row.first_name, row.last_name].filter(Boolean).join(' ') || row.student_name || '—')}</td>
      <td>${this.esc(row.class_name || '—')}</td>
      <td>${this.esc(row.complaint || '—')}</td>
      <td>${this.date(row.visit_date)} ${this.esc(row.visit_time || '')}</td>
      <td>${Number(row.referred_to_hospital) === 1 ? 'Yes' : 'No'}</td>
      <td>${this.esc(row.action_taken || (row.status === 'active' ? 'Under care' : '—'))}</td>
    </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No sick-bay visits are recorded.</td></tr>';
    this.renderKpis();
  },
  renderKpis() {
    const target = document.getElementById('hrKpis'); if (!target) return;
    const s = this.state.summary;
    const metrics = [['Active sick-bay visits', s.active_sick_bay], ['Visits today', s.visits_today], ['Hospital referrals', s.referred], ['Learners with health records', s.students_with_records], ['Vaccinations due soon', s.vaccinations_due]];
    target.innerHTML = metrics.map(([label, value]) => `<div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">${label}</small><div class="fs-3 fw-semibold">${value == null ? '—' : Number(value).toLocaleString()}</div></div></div></div>`).join('');
  },
  date(value) { if (!value) return '—'; const d = new Date(String(value).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? this.esc(value) : d.toLocaleDateString(); },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('health')) return window.showNotification?.('You do not have permission to export health data.', 'error');
    const headers = ['Admission No', 'Student', 'Class', 'Complaint', 'Visit Date', 'Visit Time', 'Referred', 'Action'];
    const quote = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = [headers, ...this.state.visits.map((r) => [r.admission_no, [r.first_name, r.last_name].filter(Boolean).join(' '), r.class_name, r.complaint, r.visit_date, r.visit_time, Number(r.referred_to_hospital) === 1 ? 'Yes' : 'No', r.action_taken])].map((row) => row.map(quote).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'health-reports.csv', 'text/csv;charset=utf-8');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('health')) return window.showNotification?.('You do not have permission to print health data.', 'error'); window.print(); },
  init() {
    document.getElementById('hrRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('hrExport')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('hrPrint')?.addEventListener('click', () => this.print());
    this.load();
    window.APIRealtime?.register?.('health_reports', { refresh: () => this.load() }, ['reports', 'health']);
  },
};
window.healthReportsController = healthReportsController;
document.addEventListener('DOMContentLoaded', () => healthReportsController.init());
