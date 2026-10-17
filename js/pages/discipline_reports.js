/**
 * Discipline Reports Controller
 * Discipline incidents, offenses, and resolution tracking.
 */
const disciplineReportsController = {
  state: { classes: [], streams: [], terms: [], offenses: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "discipline-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Discipline Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [cls, strms, terms, offenses] = await Promise.all([
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms-list'),
        cache?.offenses !== undefined ? cache.offenses : this.API('GET', '/api/students/discipline-meta'),
      ]);
      this.state.classes = this.norm(cls);
      this.state.streams = this.norm(strms);
      this.state.terms = this.norm(terms);
      const offensesRaw = this.norm(offenses);
      const offenseList = Array.isArray(offensesRaw) ? offensesRaw : (offensesRaw?.offense_types || []);
      this.state.offenses = offenseList.map(x => typeof x === 'string' ? { id: x, name: x } : x);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const c = document.getElementById('discClass');
    const s = document.getElementById('discStream');
    const t = document.getElementById('discTerm');
    const o = document.getElementById('discOffense');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    t.innerHTML = '<option value="">All Terms</option>' + this.state.terms.map(x => `<option value="${x.id}">${this.esc(x.name || x.term_name)}</option>`).join('');
    o.innerHTML = '<option value="">All Offense Types</option>' + this.state.offenses.map(x => `<option value="${x.id}">${this.esc(x.name || x.offense_name)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('discClass')?.value) params.set('class_id', document.getElementById('discClass').value);
    if (document.getElementById('discStream')?.value) params.set('stream_id', document.getElementById('discStream').value);
    if (document.getElementById('discTerm')?.value) params.set('term_id', document.getElementById('discTerm').value);
    if (document.getElementById('discOffense')?.value) params.set('offense_id', document.getElementById('discOffense').value);

    try {
      const response = await this.API('GET', '/api/reports/discipline', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('discBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No discipline data.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.full_name || '—')}</td>
      <td class="small">${this.esc(x.class_name || '—')}</td>
      <td class="small">${this.esc(x.stream_name || '—')}</td>
      <td class="small">${this.esc(x.offense_name || '—')}</td>
      <td class="small">${this.esc(x.incident_date || '—')}</td>
      <td class="small"><span class="badge bg-${x.severity === 'high' ? 'danger' : x.severity === 'medium' ? 'warning' : 'info'}">${this.esc(x.severity || '—')}</span></td>
      <td class="small">${this.esc(x.action_taken || '—')}</td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "discipline-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Discipline Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("discipline_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "discipline"]);
  },
};

window.disciplineReportsController = disciplineReportsController;
document.addEventListener('DOMContentLoaded', () => disciplineReportsController.init().catch(() => {}));