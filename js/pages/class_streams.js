/**
 * Class Streams Controller
 * Manages class streams.
 */
const classStreamsController = {
  state: { referenceCache: null, streams: [], classes: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "class-streams");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Class Streams Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [streams, classes] = await Promise.all([
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
      ]);
      this.state.streams = Array.isArray(streams?.data) ? streams.data : (Array.isArray(streams) ? streams : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.renderStreams();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderStreams() {
    const streams = this.state.streams;
    const body = document.getElementById('csStreamsBody');
    if (!streams.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No streams defined.</td></tr>'; return; }
    body.innerHTML = streams.map(s => `<tr>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.class_name || '—')}</td>
      <td class="small">${s.capacity || '—'}</td>
      <td class="small"><span class="badge bg-${s.is_active ? 'success' : 'secondary'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="classStreamsController.editStream(${s.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const classes = this.state.classes;
    const classSelect = document.getElementById('csClassSelect');
    if (classSelect) classSelect.innerHTML = '<option value="">Select Class</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
  },

  async createStream() {
    const name = document.getElementById('csName')?.value;
    const classId = document.getElementById('csClassSelect')?.value;
    const capacity = document.getElementById('csCapacity')?.value;
    if (!name || !classId) { this.notify('Name and class required', 'warning'); return; }
    try {
      await this.API('POST', '/api/academic/streams', { name, class_id: classId, capacity });
      this.notify('Stream created', 'success');
      document.getElementById('csForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Creation failed', 'danger'); }
  },

  async editStream(id) { /* open edit modal */ },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("class_streams", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.classStreamsController = classStreamsController;
document.addEventListener('DOMContentLoaded', () => classStreamsController.init().catch(() => {}));