/**
 * Import Existing Students Controller
 * Handles bulk import of existing students.
 */
const importExistingStudentsController = {
  state: { referenceCache: null, importContext: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "management-reference-data");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Import Students Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const importContext = cache?.importContext !== undefined ? cache.importContext : await this.API('GET', '/api/students/import-context');
      this.state.importContext = importContext?.data || importContext || {};
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderFormOptions() {
    const classes = this.getCache('classes') || [];
    const streams = this.getCache('streams') || [];
    const studentTypes = this.getCache('studentTypes') || [];
    const parents = this.getCache('parents') || [];
    const classSelect = document.getElementById('iesClassSelect');
    const streamSelect = document.getElementById('iesStreamSelect');
    const typeSelect = document.getElementById('iesTypeSelect');
    const parentSelect = document.getElementById('iesParentSelect');
    if (classSelect) classSelect.innerHTML = '<option value="">Select Class</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
    if (streamSelect) streamSelect.innerHTML = '<option value="">Select Stream</option>' + streams.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
    if (typeSelect) typeSelect.innerHTML = '<option value="">Select Type</option>' + studentTypes.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
    if (parentSelect) parentSelect.innerHTML = '<option value="">Select Parent</option>' + parents.map(p => `<option value="${p.id}">${this.esc(p.name)}</option>`).join('');
  },

  async importStudents() {
    const file = document.getElementById('iesFile')?.files[0];
    const classId = document.getElementById('iesClassSelect')?.value;
    const streamId = document.getElementById('iesStreamSelect')?.value;
    const typeId = document.getElementById('iesTypeSelect')?.value;
    const parentId = document.getElementById('iesParentSelect')?.value;
    if (!file) { this.notify('Please select a file', 'warning'); return; }
    const formData = new FormData();
    formData.append('file', file);
    formData.append('class_id', classId);
    formData.append('stream_id', streamId);
    formData.append('student_type_id', typeId);
    formData.append('parent_id', parentId);
    try {
      await this.API('POST', '/api/students/import', formData, {}, { headers: {} });
      this.notify('Import completed', 'success');
      document.getElementById('iesForm')?.reset();
    } catch (e) { this.notify('Import failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("import_existing_students", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["students"]);
  },
};

window.importExistingStudentsController = importExistingStudentsController;
document.addEventListener('DOMContentLoaded', () => importExistingStudentsController.init().catch(() => {}));