/**
 * Comparative Reports Controller
 * Cross-class, cross-stream, cross-subject performance comparisons.
 */
const comparativeReportsController = {
  state: { classes: [], streams: [], terms: [], subjects: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "comparative-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Comparative Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [cls, strms, terms, subs] = await Promise.all([
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms-list'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/subjects-list'),
      ]);
      this.state.classes = this.norm(cls);
      this.state.streams = this.norm(strms);
      this.state.terms = this.norm(terms);
      this.state.subjects = this.norm(subs);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const c = document.getElementById('crClass');
    const s = document.getElementById('crStream');
    const t = document.getElementById('crTerm');
    const sb = document.getElementById('crSubject');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    t.innerHTML = '<option value="">All Terms</option>' + this.state.terms.map(x => `<option value="${x.id}">${this.esc(x.name || x.term_name)}</option>`).join('');
    sb.innerHTML = '<option value="">All Subjects</option>' + this.state.subjects.map(x => `<option value="${x.id}">${this.esc(x.name || x.subject_name)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('crClass')?.value) params.set('class_id', document.getElementById('crClass').value);
    if (document.getElementById('crStream')?.value) params.set('stream_id', document.getElementById('crStream').value);
    if (document.getElementById('crTerm')?.value) params.set('term_id', document.getElementById('crTerm').value);
    if (document.getElementById('crSubject')?.value) params.set('subject_id', document.getElementById('crSubject').value);

    try {
      const response = await this.API('GET', '/api/reports/comparative', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('crBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No comparative data.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.class_name || '—')}</td>
      <td class="small">${this.esc(x.stream_name || '—')}</td>
      <td class="small">${this.esc(x.subject_name || '—')}</td>
      <td class="small text-center">${this.esc(x.student_count || 0)}</td>
      <td class="small text-center">${this.esc(x.avg_score || '—')}</td>
      <td class="small text-center">${this.esc(x.highest || '—')}</td>
      <td class="small text-center">${this.esc(x.lowest || '—')}</td>
      <td class="small text-center">${this.esc(x.pass_rate || '—')}%</td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "comparative-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Comparative Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("comparative_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "academic"]);
  },
};

window.comparativeReportsController = comparativeReportsController;
document.addEventListener('DOMContentLoaded', () => comparativeReportsController.init().catch(() => {}));