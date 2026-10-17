/**
 * Participation Reports Controller
 * Activity participation, engagement, and attendance reports.
 */
const participationReportsController = {
  state: { activities: [], participants: [], classes: [], referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "participation-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Participation Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [acts, parts, classes] = await Promise.all([
        cache?.activities !== undefined ? cache.activities : this.API('GET', '/api/activities/list'),
        cache?.participants !== undefined ? cache.participants : this.API('GET', '/api/activities/participants-list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
      ]);
      this.state.activities = this.norm(acts);
      this.state.participants = this.norm(parts);
      this.state.classes = this.norm(classes);
      this.populateFilters();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const a = document.getElementById('prActivity');
    const c = document.getElementById('prClass');
    a.innerHTML = '<option value="">All Activities</option>' + this.state.activities.map(x => `<option value="${x.id}">${this.esc(x.name || x.activity_name)}</option>`).join('');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "participation-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Participation Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("participation_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
      }
    }, ["reports", "activities"]);
  },
};

window.participationReportsController = participationReportsController;
document.addEventListener('DOMContentLoaded', () => participationReportsController.init().catch(() => {}));