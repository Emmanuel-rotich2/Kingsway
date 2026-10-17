/**
 * Enrollment Reports Controller
 * Student enrollment, class placement, and transition reports.
 */
const enrollmentReportsController = {
  state: { classes: [], streams: [], years: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "enrollment-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Enrollment Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [cls, strms, years] = await Promise.all([
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
        cache?.years !== undefined ? cache.years : this.API('GET', '/api/academic/years/list'),
      ]);
      this.state.classes = this.norm(cls);
      this.state.streams = this.norm(strms);
      this.state.years = this.norm(years);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const c = document.getElementById('erClass');
    const s = document.getElementById('erStream');
    const y = document.getElementById('erYear');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    y.innerHTML = '<option value="">All Years</option>' + this.state.years.map(x => `<option value="${x.id}">${this.esc(x.year_name || x.year_code)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('erClass')?.value) params.set('class_id', document.getElementById('erClass').value);
    if (document.getElementById('erStream')?.value) params.set('stream_id', document.getElementById('erStream').value);
    if (document.getElementById('erYear')?.value) params.set('year_id', document.getElementById('erYear').value);

    try {
      const response = await this.API('GET', '/api/reports/enrollment', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('erBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No enrollment data.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.full_name || '—')}</td>
      <td class="small">${this.esc(x.class_name || '—')}</td>
      <td class="small">${this.esc(x.stream_name || '—')}</td>
      <td class="small">${this.esc(x.admission_date || '—')}</td>
      <td class="small">${this.esc(x.student_type || '—')}</td>
      <td class="small">${this.esc(x.status || '—')}</td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "enrollment-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Enrollment Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("enrollment_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "admissions"]);
  },
};

window.enrollmentReportsController = enrollmentReportsController;
document.addEventListener('DOMContentLoaded', () => enrollmentReportsController.init().catch(() => {}));