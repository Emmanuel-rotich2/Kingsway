/**
 * Attendance Reports Controller
 * Shows attendance statistics, trends, and student-level detail.
 */
const attendanceReportsController = {
  state: { classes: [], streams: [], terms: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "attendance-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Attendance Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('atBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const [cls, strms, terms] = await Promise.all([
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms-list'),
      ]);
      this.state.classes = this.norm(cls);
      this.state.streams = this.norm(strms);
      this.state.terms = this.norm(terms);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const c = document.getElementById('arClass');
    const s = document.getElementById('arStream');
    const t = document.getElementById('arTerm');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    t.innerHTML = '<option value="">All Terms</option>' + this.state.terms.map(x => `<option value="${x.id}">${this.esc(x.name || x.term_name)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('arClass')?.value) params.set('class_id', document.getElementById('arClass').value);
    if (document.getElementById('arStream')?.value) params.set('stream_id', document.getElementById('arStream').value);
    if (document.getElementById('arTerm')?.value) params.set('term_id', document.getElementById('arTerm').value);

    try {
      const response = await this.API('GET', '/api/reports/attendance', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('atBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No attendance data.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.full_name || '—')}</td>
      <td class="small">${this.esc(x.class_name || '—')}</td>
      <td class="small">${this.esc(x.stream_name || '—')}</td>
      <td class="small text-center">${this.esc(x.present_days || 0)}</td>
      <td class="small text-center">${this.esc(x.absent_days || 0)}</td>
      <td class="small text-center">${this.esc(x.late_days || 0)}</td>
      <td class="small text-center fw-semibold">${this.esc(x.rate || '—')}%</td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "attendance-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Attendance Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("attendance_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "attendance"]);
  },
};

window.attendanceReportsController = attendanceReportsController;
document.addEventListener('DOMContentLoaded', () => attendanceReportsController.init().catch(() => {}));