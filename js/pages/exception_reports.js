/** Finance exception report for reviewable fee credit adjustments. */
const exceptionReportsController = {
  state: { exceptions: [], stats: {}, severity: 'all' },
  API: (method, endpoint, data = null, params = null) => window.callAPI(endpoint, method, data, params),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  payload(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  async init() {
    document.querySelectorAll('#erSeverityTabs button').forEach((button) => button.addEventListener('click', () => this.filterBySeverity(button.dataset.severity || 'all', button)));
    document.getElementById('erRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('erExport')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('erPrint')?.addEventListener('click', () => this.print());
    await this.load();
  },
  async load() {
    const body = document.getElementById('erTableBody');
    if (body) body.innerHTML = '<tr><td colspan="7" class="text-center py-4">Loading finance exceptions…</td></tr>';
    const error = document.getElementById('erError'); error?.classList.add('d-none');
    try {
      const result = this.payload(await this.API('GET', '/api/finance/exception-reports'));
      this.state.exceptions = Array.isArray(result.exceptions) ? result.exceptions : [];
      this.state.stats = result.stats || {};
      this.render();
    } catch (exception) {
      if (body) body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Finance exceptions could not be loaded.</td></tr>';
      if (error) { error.textContent = exception.message || 'Finance exceptions could not be loaded.'; error.classList.remove('d-none'); }
    }
  },
  filterBySeverity(severity, button) {
    this.state.severity = severity;
    document.querySelectorAll('#erSeverityTabs .nav-link').forEach((item) => item.classList.toggle('active', item === button));
    this.render();
  },
  render() {
    const set = (id, value) => { const item = document.getElementById(id); if (item) item.textContent = String(value ?? 0); };
    set('erStatTotal', this.state.stats.total); set('erStatUnmatched', this.state.stats.open_count); set('erStatCritical', this.state.stats.high_count);
    set('erStatHigh', this.state.exceptions.filter((item) => item.status === 'dismissed').length);
    const rows = this.state.severity === 'all' ? this.state.exceptions : this.state.exceptions.filter((item) => item.severity === this.state.severity);
    const body = document.getElementById('erTableBody'); if (!body) return;
    body.innerHTML = rows.length ? rows.map((item) => `<tr><td>${this.esc(item.reference || '—')}</td><td>${this.esc(item.exception_type || '—')}</td><td>${this.esc(item.description || '—')}</td><td>${Number(item.amount || 0).toLocaleString('en-KE',{style:'currency',currency:'KES'})}</td><td>${this.esc(item.affected_party || '—')}</td><td>${this.esc(item.detected_at || '—')}</td><td><span class="badge text-bg-${item.status === 'open' ? 'warning' : 'secondary'}">${this.esc(item.status || '—')}</span></td></tr>`).join('') : '<tr><td colspan="7" class="text-center py-4 text-muted">No finance exceptions match this filter.</td></tr>';
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('finance')) return window.showNotification?.('You do not have permission to export finance data.', 'error');
    const rows = [['Reference','Exception Type','Description','Amount','Affected Party','Detected','Status'], ...this.state.exceptions.map((item) => [item.reference,item.exception_type,item.description,item.amount,item.affected_party,item.detected_at,item.status])];
    const quote = (value) => `"${String(value ?? '').replaceAll('"','""')}"`;
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'finance-exceptions.csv', 'text/csv;charset=utf-8;');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('finance')) return window.showNotification?.('You do not have permission to print finance data.', 'error'); window.print(); },
};
window.exceptionReportsController = exceptionReportsController;
document.addEventListener('DOMContentLoaded', () => exceptionReportsController.init());
