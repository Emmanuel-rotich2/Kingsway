/**
 * Performance Analysis Controller
 * Analyzes academic performance trends and patterns.
 */
const performanceAnalysisController = {
  state: { referenceCache: null, analysis: null, trends: [], years: [], classes: [], subjects: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "performance-trends-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Performance Analysis Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [analysis, trends, years, classes, subjects] = await Promise.all([
        cache?.analysis !== undefined ? cache.analysis : this.API('GET', '/api/academic/performance-analysis'),
        cache?.trends !== undefined ? cache.trends : this.API('GET', '/api/academic/performance-trends'),
        cache?.years !== undefined ? cache.years : this.API('GET', '/api/academic/years/list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/learning-areas/list'),
      ]);
      this.state.analysis = analysis?.data || analysis || {};
      this.state.trends = Array.isArray(trends?.data) ? trends.data : (Array.isArray(trends) ? trends : []);
      this.state.years = Array.isArray(years?.data) ? years.data : (Array.isArray(years) ? years : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.renderAnalysis();
      this.renderTrends();
      this.renderFilters();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderAnalysis() {
    const a = this.state.analysis;
    document.getElementById('paOverallMean')?.textContent = a?.overall_mean ?? '—';
    document.getElementById('paImprovementRate')?.textContent = `${a?.improvement_rate ?? '—'}%`;
    document.getElementById('paTopSubject')?.textContent = a?.top_subject || '—';
    document.getElementById('paBottomSubject')?.textContent = a?.bottom_subject || '—';
    document.getElementById('paTopClass')?.textContent = a?.top_class || '—';
  },

  renderTrends() {
    const trends = this.state.trends;
    const body = document.getElementById('paTrendsBody');
    if (!trends.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No trend data.</td></tr>'; return; }
    body.innerHTML = trends.map(t => `<tr>
      <td class="small">${this.esc(t.period || '—')}</td>
      <td class="small">${this.esc(t.subject || 'Overall')}</td>
      <td class="small text-center">${this.esc(t.mean_score || '—')}</td>
      <td class="small text-center">${t.change >= 0 ? '+' : ''}${this.esc(t.change || '—')}%</td>
      <td class="small"><span class="badge bg-${t.change >= 0 ? 'success' : 'danger'}">${t.change >= 0 ? 'Improving' : 'Declining'}</span></td>
    </tr>`).join('');
  },

  renderFilters() {
    const years = this.state.years;
    const classes = this.state.classes;
    const subjects = this.state.subjects;
    const yearSelect = document.getElementById('paYearFilter');
    const classSelect = document.getElementById('paClassFilter');
    const subjectSelect = document.getElementById('paSubjectFilter');
    if (yearSelect) yearSelect.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name)}</option>`).join('');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">All Subjects</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("performance_analysis", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic", "reports"]);
  },
};

window.performanceAnalysisController = performanceAnalysisController;
document.addEventListener('DOMContentLoaded', () => performanceAnalysisController.init().catch(() => {}));