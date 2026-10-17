/**
 * Generate Subject Report Controller
 * Generates subject-level academic reports.
 */
const generateSubjectReportController = {
  state: { classes: [], streams: [], terms: [], subjects: [], referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "generate-subject-report-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Generate Subject Report Node view failed; using direct reads:", error);
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
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const c = document.getElementById('gsrClass');
    const s = document.getElementById('gsrStream');
    const t = document.getElementById('gsrTerm');
    const sb = document.getElementById('gsrSubject');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    t.innerHTML = '<option value="">All Terms</option>' + this.state.terms.map(x => `<option value="${x.id}">${this.esc(x.name || x.term_name)}</option>`).join('');
    sb.innerHTML = '<option value="">All Subjects</option>' + this.state.subjects.map(x => `<option value="${x.id}">${this.esc(x.name || x.subject_name)}</option>`).join('');
  },

  async generate() {
    const c = document.getElementById('gsrClass')?.value;
    const s = document.getElementById('gsrStream')?.value;
    const t = document.getElementById('gsrTerm')?.value;
    const sb = document.getElementById('gsrSubject')?.value;
    if (!c || !t || !sb) return this.notify('Select class, term, and subject', 'warning');

    const body = document.getElementById('gsrBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
      const response = await this.API('POST', '/api/reports/generate-subject-report', { class_id: c, stream_id: s || null, term_id: t, subject_id: sb });
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Generation failed')}</td></tr>`; }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('gsrBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No report generated.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.full_name || '—')}</td>
      <td class="small">${this.esc(x.class_name || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.score || '—')}</td>
      <td class="small"><span class="badge bg-${this.gradeBadge(x.grade)}">${this.esc(x.grade || '—')}</span></td>
      <td class="small">${this.esc(x.term_name || '—')}</td>
      <td class="small">${this.esc(x.teacher_name || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="generateSubjectReportController.viewDetails(${x.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  gradeBadge(g) { return { 'A': 'success', 'B': 'primary', 'C': 'info', 'D': 'warning', 'E': 'danger' }[g] || 'secondary'; },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "generate-subject-report-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Generate Subject Report Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("generate_subject_report", {
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

window.generateSubjectReportController = generateSubjectReportController;
document.addEventListener('DOMContentLoaded', () => generateSubjectReportController.init().catch(() => {}));