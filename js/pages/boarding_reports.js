/**
 * Boarding Reports Controller
 * Generates and views boarding-related reports.
 */
const boardingReportsController = {
  state: { referenceCache: null, reports: [], stats: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("boarding", "boarding-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Boarding Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [reports, stats] = await Promise.all([
        cache?.reports !== undefined ? cache.reports : this.API('GET', '/api/boarding/reports'),
        cache?.stats !== undefined ? cache.stats : this.API('GET', '/api/boarding/stats'),
      ]);
      this.state.reports = Array.isArray(reports?.data) ? reports.data : (Array.isArray(reports) ? reports : []);
      this.state.stats = stats?.data || stats || {};
      this.renderStats();
      this.renderReports();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderStats() {
    const s = this.state.stats;
    document.getElementById('brTotalBoarders')?.textContent = s.total_boarders ?? '—';
    document.getElementById('brOccupancyRate')?.textContent = `${s.occupancy_rate ?? '—'}%`;
    document.getElementById('brAttendanceRate')?.textContent = `${s.attendance_rate ?? '—'}%`;
    document.getElementById('brIncidents')?.textContent = s.incidents_this_term ?? '—';
  },

  renderReports() {
    const reports = this.state.reports;
    const body = document.getElementById('brReportsBody');
    if (!reports.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No reports available.</td></tr>'; return; }
    body.innerHTML = reports.map(r => `<tr>
      <td class="small">${this.esc(r.title || '—')}</td>
      <td class="small">${this.fmtDate(r.period_start)} - ${this.fmtDate(r.period_end)}</td>
      <td class="small">${this.esc(r.generated_by || '—')}</td>
      <td><a href="${r.pdf_url}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-file-pdf"></i> Download</a></td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("boarding_reports", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["boarding", "reports"]);
  },
};

window.boardingReportsController = boardingReportsController;
document.addEventListener('DOMContentLoaded', () => boardingReportsController.init().catch(() => {}));