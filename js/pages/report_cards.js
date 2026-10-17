/**
 * Report Cards Controller
 * Generates and manages student report cards.
 */
const reportCardsController = {
  state: { classes: [], streams: [], terms: [], students: [], referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "report-cards-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Report Cards Node view failed; using direct reads:", error);
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
    const c = document.getElementById('rcClass');
    const s = document.getElementById('rcStream');
    const t = document.getElementById('rcTerm');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    t.innerHTML = '<option value="">All Terms</option>' + this.state.terms.map(x => `<option value="${x.id}">${this.esc(x.name || x.term_name)}</option>`).join('');
  },

  async generate() {
    const c = document.getElementById('rcClass')?.value;
    const s = document.getElementById('rcStream')?.value;
    const t = document.getElementById('rcTerm')?.value;
    if (!c || !t) return this.notify('Select class and term', 'warning');

    const body = document.getElementById('rcBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
      const response = await this.API('POST', '/api/reports/generate-report-cards', { class_id: c, stream_id: s || null, term_id: t });
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Generation failed')}</td></tr>`; }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('rcBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No report cards generated.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.full_name || '—')}</td>
      <td class="small">${this.esc(x.subject_name || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.score || '—')}</td>
      <td class="small"><span class="badge bg-${this.gradeBadge(x.grade)}">${this.esc(x.grade || '—')}</span></td>
      <td class="small">${this.esc(x.term_name || '—')}</td>
      <td class="small">${this.esc(x.teacher_name || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="reportCardsController.viewDetails(${x.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  gradeBadge(g) { return { 'A': 'success', 'B': 'primary', 'C': 'info', 'D': 'warning', 'E': 'danger' }[g] || 'secondary'; },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "report-cards-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Report Cards Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("report_cards", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
      }
    }, ["reports", "academic"]);
  },

  notify(msg, type = 'info') {
    if (window.showNotification) showNotification(msg, type);
    else alert(msg);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },
  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },
};

window.reportCardsController = reportCardsController;
document.addEventListener('DOMContentLoaded', () => reportCardsController.init().catch(() => {}));