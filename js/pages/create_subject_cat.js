/**
 * Create Subject Category Controller
 * Manages subject categories for CBC curriculum.
 */
const createSubjectCatController = {
  state: { referenceCache: null, categories: [], subjects: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "curriculum-cbc-reference");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Create Subject Category Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [categories, subjects] = await Promise.all([
        cache?.categories !== undefined ? cache.categories : this.API('GET', '/api/academic/subject-categories'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/learning-areas'),
      ]);
      this.state.categories = Array.isArray(categories?.data) ? categories.data : (Array.isArray(categories) ? categories : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.renderCategories();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderCategories() {
    const categories = this.state.categories;
    const body = document.getElementById('cscCategoriesBody');
    if (!categories.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No categories defined.</td></tr>'; return; }
    body.innerHTML = categories.map(c => `<tr>
      <td class="small">${this.esc(c.name || '—')}</td>
      <td class="small">${this.esc(c.description || '—')}</td>
      <td class="small">${c.subjects_count || 0} subjects</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="createSubjectCatController.editCategory(${c.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const subjects = this.state.subjects;
    const subjectSelect = document.getElementById('cscSubjectSelect');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">Select Subject</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  async createCategory() {
    const name = document.getElementById('cscName')?.value;
    const description = document.getElementById('cscDescription')?.value;
    const subjectId = document.getElementById('cscSubjectSelect')?.value;
    if (!name) { this.notify('Name required', 'warning'); return; }
    try {
      await this.API('POST', '/api/academic/subject-categories', { name, description, subject_id: subjectId });
      this.notify('Category created', 'success');
      document.getElementById('cscForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Creation failed', 'danger'); }
  },

  async editCategory(id) { /* open edit modal */ },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("create_subject_cat", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.createSubjectCatController = createSubjectCatController;
document.addEventListener('DOMContentLoaded', () => createSubjectCatController.init().catch(() => {}));