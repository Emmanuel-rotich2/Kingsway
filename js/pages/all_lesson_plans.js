/**
 * All Lesson Plans Controller
 * Comprehensive view of all lesson plans.
 */
const allLessonPlansController = {
  state: { referenceCache: null, lessonPlans: [], teachers: [], classes: [], subjects: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "manage-timetable-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("All Lesson Plans Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [lessonPlans, teachers, classes, subjects] = await Promise.all([
        cache?.lessonPlans !== undefined ? cache.lessonPlans : this.API('GET', '/api/academic/all-lesson-plans'),
        cache?.teachers !== undefined ? cache.teachers : this.API('GET', '/api/academic/teachers-list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/learning-areas/list'),
      ]);
      this.state.lessonPlans = Array.isArray(lessonPlans?.data) ? lessonPlans.data : (Array.isArray(lessonPlans) ? lessonPlans : []);
      this.state.teachers = Array.isArray(teachers?.data) ? teachers.data : (Array.isArray(teachers) ? teachers : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.renderLessonPlans();
      this.renderFilters();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderLessonPlans() {
    const plans = this.state.lessonPlans;
    const body = document.getElementById('alpPlansBody');
    if (!plans.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No lesson plans.</td></tr>'; return; }
    body.innerHTML = plans.map(p => `<tr>
      <td class="small">${this.esc(p.teacher_name || '—')}</td>
      <td class="small">${this.esc(p.class_name || '—')}</td>
      <td class="small">${this.esc(p.subject || '—')}</td>
      <td class="small">${this.esc(p.topic || '—')}</td>
      <td class="small">${this.fmtDate(p.week_start)} - ${this.fmtDate(p.week_end)}</td>
      <td class="small"><span class="badge bg-${p.status === 'approved' ? 'success' : p.status === 'draft' ? 'warning' : 'secondary'}">${this.esc(p.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="allLessonPlansController.viewPlan(${p.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  renderFilters() {
    const teachers = this.state.teachers;
    const classes = this.state.classes;
    const subjects = this.state.subjects;
    const teacherSelect = document.getElementById('alpTeacherFilter');
    const classSelect = document.getElementById('alpClassFilter');
    const subjectSelect = document.getElementById('alpSubjectFilter');
    if (teacherSelect) teacherSelect.innerHTML = '<option value="">All Teachers</option>' + teachers.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">All Subjects</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async viewPlan(id) { /* open plan detail modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("all_lesson_plans", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.allLessonPlansController = allLessonPlansController;
document.addEventListener('DOMContentLoaded', () => allLessonPlansController.init().catch(() => {}));