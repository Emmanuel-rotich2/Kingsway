/**
 * Class Report Cards Controller
 * Generates and manages class-level report cards.
 */
const classReportCardsController = {
  state: { classes: [], streams: [], terms: [], students: [], referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "class-report-cards-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Class Report Cards Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [cls, strms, terms, students] = await Promise.all([
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms-list'),
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/students/get-all', null, { limit: '500', status: 'active' }),
      ]);
      this.state.classes = this.norm(cls);
      this.state.streams = this.norm(strms);
      this.state.terms = this.norm(terms);
      this.state.students = this.norm(students);
      this.populateFilters();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const c = document.getElementById('crcClass');
    const s = document.getElementById('crcStream');
    const t = document.getElementById('crcTerm');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    t.innerHTML = '<option value="">All Terms</option>' + this.state.terms.map(x => `<option value="${x.id}">${this.esc(x.name || x.term_name)}</option>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "class-report-cards-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Class Report Cards Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("class_report_cards", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "academic"]);
  },
};

window.classReportCardsController = classReportCardsController;
document.addEventListener('DOMContentLoaded', () => classReportCardsController.init().catch(() => {}));