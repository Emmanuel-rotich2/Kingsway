/**
 * Manage Subjects Controller
 * Manages learning areas/subjects.
 */
const manageSubjectsController = {
  state: { referenceCache: null, subjects: [], categories: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "curriculum-cbc-reference");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Manage Subjects Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [subjects, categories] = await Promise.all([
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/learning-areas'),
        cache?.categories !== undefined ? cache.categories : this.API('GET', '/api/academic/subject-categories'),
      ]);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.state.categories = Array.isArray(categories?.data) ? categories.data : (Array.isArray(categories) ? categories : []);
      this.renderSubjects();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderSubjects() {
    const subjects = this.state.subjects;
    const body = document.getElementById('msSubjectsBody');
    if (!subjects.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No subjects defined.</td></tr>'; return; }
    body.innerHTML = subjects.map(s => `<tr>
      <td class="small">${this.esc(s.code || '—')}</td>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.category || '—')}</td>
      <td class="small"><span class="badge bg-${s.is_active ? 'success' : 'secondary'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="manageSubjectsController.editSubject(${s.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const categories = this.state.categories;
    const categorySelect = document.getElementById('msCategorySelect');
    if (categorySelect) categorySelect.innerHTML = '<option value="">Select Category</option>' + categories.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
  },

  async createSubject() {
    const code = document.getElementById('msCode')?.value;
    const name = document.getElementById('msName')?.value;
    const category = document.getElementById('msCategorySelect')?.value;
    if (!code || !name) { this.notify('Code and name required', 'warning'); return; }
    try {
      await this.API('POST', '/api/academic/learning-areas', { code, name, category_id: category });
      this.notify('Subject created', 'success');
      document.getElementById('msForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Creation failed', 'danger'); }
  },

  async editSubject(id) { /* open edit modal */ },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("manage_subjects", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.manageSubjectsController = manageSubjectsController;
document.addEventListener('DOMContentLoaded', () => manageSubjectsController.init().catch(() => {}));