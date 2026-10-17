/**
 * Class Results Controller
 * Manages and displays class-level results.
 */
const classResultsController = {
  state: { referenceCache: null, results: [], classes: [], subjects: [], terms: [], years: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "class-results-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Class Results Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [results, classes, subjects, terms, years] = await Promise.all([
        cache?.results !== undefined ? cache.results : this.API('GET', '/api/academic/class-results'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/subjects-list'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms-list'),
        cache?.years !== undefined ? cache.years : this.API('GET', '/api/academic/years-list'),
      ]);
      this.state.results = Array.isArray(results?.data) ? results.data : (Array.isArray(results) ? results : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.state.terms = Array.isArray(terms?.data) ? terms.data : (Array.isArray(terms) ? terms : []);
      this.state.years = Array.isArray(years?.data) ? years.data : (Array.isArray(years) ? years : []);
      this.renderResults();
      this.renderFilters();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderResults() {
    const results = this.state.results;
    const body = document.getElementById('crResultsBody');
    if (!results.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No results available.</td></tr>'; return; }
    body.innerHTML = results.map(r => `<tr>
      <td class="small">${this.esc(r.class_name || '—')}</td>
      <td class="small">${this.esc(r.subject || '—')}</td>
      <td class="small">${this.esc(r.term || '—')}</td>
      <td class="small text-center">${this.esc(r.mean_score || '—')}</td>
      <td class="small text-center">${this.esc(r.highest_score || '—')}</td>
      <td class="small text-center">${this.esc(r.lowest_score || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="classResultsController.viewDetails(${r.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  renderFilters() {
    const classes = this.state.classes;
    const subjects = this.state.subjects;
    const terms = this.state.terms;
    const years = this.state.years;
    const classSelect = document.getElementById('crClassFilter');
    const subjectSelect = document.getElementById('crSubjectFilter');
    const termSelect = document.getElementById('crTermFilter');
    const yearSelect = document.getElementById('crYearFilter');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">All Subjects</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
    if (termSelect) termSelect.innerHTML = '<option value="">All Terms</option>' + terms.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
    if (yearSelect) yearSelect.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name)}</option>`).join('');
  },

  async viewDetails(id) { /* open detail modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("class_results", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic", "reports"]);
  },
};

window.classResultsController = classResultsController;
document.addEventListener('DOMContentLoaded', () => classResultsController.init().catch(() => {}));