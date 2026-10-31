/** Discipline reports from the authenticated student discipline cases endpoint. */
const disciplineReportsController = {
  state: { rows: [], request: 0 },
  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  rows(response) {
    const data = response?.data?.data ?? response?.data ?? response;
    return Array.isArray(data) ? data : [];
  },
  async load() {
    const request = ++this.state.request;
    const error = document.getElementById('drError'); if (error) error.classList.add('d-none');
    const body = document.getElementById('drBody'); if (body) body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr>';
    try {
      const response = await this.api('GET', '/api/students/discipline-cases');
      if (request !== this.state.request) return;
      if (response?.success === false || response?.error) throw new Error(response.message || response.error || 'Could not load discipline cases.');
      this.state.rows = this.rows(response);
      this.render();
    } catch (e) {
      if (request !== this.state.request) return;
      if (body) body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Discipline data could not be loaded.</td></tr>';
      if (error) { error.textContent = e?.message || 'Could not load discipline reports. Refresh to try again.'; error.classList.remove('d-none'); }
      this.renderSummary([]);
    }
  },
  render() {
    const rows = this.state.rows; const body = document.getElementById('drBody');
    if (body) body.innerHTML = rows.length ? rows.map((row) => {
      const severity = String(row.severity || '').toLowerCase();
      const badge = severity === 'high' ? 'danger' : severity === 'medium' ? 'warning' : 'secondary';
      return `<tr><td>${this.esc(row.full_name || '—')}</td><td>${this.esc(row.admission_no || '—')}</td><td>${this.esc(row.class_name || '—')}</td><td>${this.esc(row.description || 'Disciplinary incident')}</td><td><span class="badge text-bg-${badge}">${this.esc(row.severity || '—')}</span></td><td>${this.date(row.incident_date)}</td><td>${this.esc(row.status || '—')}</td></tr>`;
    }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No discipline cases are recorded.</td></tr>';
    this.renderSummary(rows);
  },
  renderSummary(rows) {
    const kpis = document.getElementById('drKpis');
    if (kpis) {
      const metrics = [
        ['Total cases', rows.length],
        ['Pending', rows.filter((x) => String(x.status).toLowerCase() === 'pending').length],
        ['Escalated', rows.filter((x) => String(x.status).toLowerCase() === 'escalated').length],
        ['High severity', rows.filter((x) => String(x.severity).toLowerCase() === 'high').length],
      ];
      kpis.innerHTML = metrics.map(([label, value]) => `<div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">${label}</small><div class="fs-3 fw-semibold">${value}</div></div></div></div>`).join('');
    }
    const statusCounts = new Map(); rows.forEach((x) => { const key = `${x.severity || 'Unspecified'} · ${x.status || 'Unspecified'}`; statusCounts.set(key, (statusCounts.get(key) || 0) + 1); });
    const byType = document.getElementById('drByType');
    if (byType) byType.innerHTML = statusCounts.size ? [...statusCounts.entries()].map(([label, count]) => { const [severity, status] = label.split(' · '); return `<tr><td>${this.esc(severity)}</td><td>${this.esc(status)}</td><td>${count}</td></tr>`; }).join('') : '<tr><td colspan="3" class="text-center text-muted py-4">No cases to summarize.</td></tr>';
    const monthCounts = new Map(); rows.forEach((x) => { if (!x.incident_date) return; const month = String(x.incident_date).slice(0, 7); monthCounts.set(month, (monthCounts.get(month) || 0) + 1); });
    const max = Math.max(1, ...monthCounts.values()); const trend = document.getElementById('drTrend');
    if (trend) trend.innerHTML = monthCounts.size ? [...monthCounts.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([month, count]) => `<tr><td>${this.esc(month)}</td><td>${count}</td><td><div class="progress" role="progressbar" aria-label="${this.esc(month)} cases"><div class="progress-bar bg-danger" style="width:${Math.round(count / max * 100)}%"></div></div></td></tr>`).join('') : '<tr><td colspan="3" class="text-center text-muted py-4">No dated cases to summarize.</td></tr>';
  },
  date(value) { if (!value) return '—'; const d = new Date(String(value).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? this.esc(value) : d.toLocaleDateString(); },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('discipline')) return window.showNotification?.('You do not have permission to export discipline data.', 'error');
    const columns = ['full_name', 'admission_no', 'class_name', 'description', 'severity', 'incident_date', 'status'];
    const headers = ['Student', 'Admission No', 'Class', 'Incident Summary', 'Severity', 'Date', 'Status'];
    const quote = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = [headers, ...this.state.rows.map((row) => columns.map((key) => row[key] ?? ''))].map((line) => line.map(quote).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'discipline-reports.csv', 'text/csv;charset=utf-8');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('discipline')) return window.showNotification?.('You do not have permission to print discipline data.', 'error'); window.print(); },
  init() {
    document.getElementById('drRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('drExport')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('drPrint')?.addEventListener('click', () => this.print());
    this.load();
    window.APIRealtime?.register?.('discipline_reports', { refresh: () => this.load() }, ['reports', 'discipline']);
  },
};
window.disciplineReportsController = disciplineReportsController;
document.addEventListener('DOMContentLoaded', () => disciplineReportsController.init());
