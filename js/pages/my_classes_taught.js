/**
 * My Classes Taught Controller
 * Teacher view of their assigned classes and subjects.
 */
const myClassesTaughtController = {
  state: { referenceCache: null, classes: [], subjects: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "teacher-performance-reviews-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("My Classes Taught Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [classes, subjects] = await Promise.all([
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/teacher/my-classes'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/teacher/my-subjects'),
      ]);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.renderClasses();
      this.renderSubjects();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderClasses() {
    const classes = this.state.classes;
    const body = document.getElementById('mctClassesBody');
    if (!classes.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No assigned classes.</td></tr>'; return; }
    body.innerHTML = classes.map(c => `<tr>
      <td class="small">${this.esc(c.name || '—')}</td>
      <td class="small">${this.esc(c.stream_name || '—')}</td>
      <td class="small text-center">${c.student_count || 0}</td>
      <td class="small">${this.esc(c.subjects_taught || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="myClassesTaughtController.viewClass(${c.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  renderSubjects() {
    const subjects = this.state.subjects;
    const body = document.getElementById('mctSubjectsBody');
    if (!subjects.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No assigned subjects.</td></tr>'; return; }
    body.innerHTML = subjects.map(s => `<tr>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.classes_taught || '—')}</td>
      <td class="small text-center">${s.lessons_this_week || 0}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="myClassesTaughtController.viewSubject(${s.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  async viewClass(id) { /* open class detail */ },
  async viewSubject(id) { /* open subject detail */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("my_classes_taught", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.myClassesTaughtController = myClassesTaughtController;
document.addEventListener('DOMContentLoaded', () => myClassesTaughtController.init().catch(() => {}));