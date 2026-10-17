/**
 * Subject Exam Schedule Controller
 * Manages exam schedules by subject.
 */
const subjectExamScheduleController = {
  state: { referenceCache: null, schedules: [], subjects: [], classes: [], terms: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "exam-setup-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Subject Exam Schedule Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [schedules, subjects, classes, terms] = await Promise.all([
        cache?.schedules !== undefined ? cache.schedules : this.API('GET', '/api/academic/subject-exam-schedule'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/learning-areas/list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes/list'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms/list'),
      ]);
      this.state.schedules = Array.isArray(schedules?.data) ? schedules.data : (Array.isArray(schedules) ? schedules : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.state.terms = Array.isArray(terms?.data) ? terms.data : (Array.isArray(terms) ? terms : []);
      this.renderSchedules();
      this.renderFilters();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderSchedules() {
    const schedules = this.state.schedules;
    const body = document.getElementById('sesSchedulesBody');
    if (!schedules.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No exam schedules.</td></tr>'; return; }
    body.innerHTML = schedules.map(s => `<tr>
      <td class="small">${this.esc(s.subject || '—')}</td>
      <td class="small">${this.esc(s.class_name || '—')}</td>
      <td class="small">${this.esc(s.term || '—')}</td>
      <td class="small">${this.fmtDate(s.exam_date)}</td>
      <td class="small">${this.esc(s.start_time || '—')}</td>
      <td class="small">${this.esc(s.duration || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="subjectExamScheduleController.editSchedule(${s.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFilters() {
    const subjects = this.state.subjects;
    const classes = this.state.classes;
    const terms = this.state.terms;
    const subjectSelect = document.getElementById('sesSubjectFilter');
    const classSelect = document.getElementById('sesClassFilter');
    const termSelect = document.getElementById('sesTermFilter');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">All Subjects</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
    if (termSelect) termSelect.innerHTML = '<option value="">All Terms</option>' + terms.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async editSchedule(id) { /* open edit modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("subject_exam_schedule", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.subjectExamScheduleController = subjectExamScheduleController;
document.addEventListener('DOMContentLoaded', () => subjectExamScheduleController.init().catch(() => {}));