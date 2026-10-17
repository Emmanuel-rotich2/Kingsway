/**
 * National Exams Controller
 * Manages national examination data and schedules.
 */
const nationalExamsController = {
  state: { referenceCache: null, exams: [], years: [], subjects: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "view-results-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("National Exams Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [exams, years, subjects] = await Promise.all([
        cache?.exams !== undefined ? cache.exams : this.API('GET', '/api/academic/national-exams'),
        cache?.years !== undefined ? cache.years : this.API('GET', '/api/academic/years/list'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/learning-areas/list'),
      ]);
      this.state.exams = Array.isArray(exams?.data) ? exams.data : (Array.isArray(exams) ? exams : []);
      this.state.years = Array.isArray(years?.data) ? years.data : (Array.isArray(years) ? years : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.renderExams();
      this.renderFilters();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderExams() {
    const exams = this.state.exams;
    const body = document.getElementById('neExamsBody');
    if (!exams.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No national exams scheduled.</td></tr>'; return; }
    body.innerHTML = exams.map(e => `<tr>
      <td class="small">${this.esc(e.name || '—')}</td>
      <td class="small">${this.esc(e.year || '—')}</td>
      <td class="small">${this.esc(e.subject || '—')}</td>
      <td class="small">${this.fmtDate(e.exam_date)}</td>
      <td class="small"><span class="badge bg-${e.status === 'scheduled' ? 'info' : e.status === 'completed' ? 'success' : 'secondary'}">${this.esc(e.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="nationalExamsController.viewExam(${e.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  renderFilters() {
    const years = this.state.years;
    const subjects = this.state.subjects;
    const yearSelect = document.getElementById('neYearFilter');
    const subjectSelect = document.getElementById('neSubjectFilter');
    if (yearSelect) yearSelect.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name)}</option>`).join('');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">All Subjects</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async viewExam(id) { /* open exam detail modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("national_exams", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.nationalExamsController = nationalExamsController;
document.addEventListener('DOMContentLoaded', () => nationalExamsController.init().catch(() => {}));