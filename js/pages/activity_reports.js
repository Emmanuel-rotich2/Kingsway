/**
 * Activity Reports Controller
 * Shows participation, attendance, and engagement reports for activities.
 * All reads through Node gateway for pooling, caching, aggregation.
 */
const activityReportsController = {
  state: { activities: [], participants: [], types: [], referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.activities || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "activity-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Activity Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('arBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const [acts, parts, types] = await Promise.all([
        cache?.activities !== undefined ? cache.activities : this.API('GET', '/api/activities/list'),
        cache?.participants !== undefined ? cache.participants : this.API('GET', '/api/activities/participants-list'),
        cache?.types !== undefined ? cache.types : this.API('GET', '/api/activities/categories-list'),
      ]);
      this.state.activities = this.norm(acts);
      this.state.participants = this.norm(parts);
      this.state.types = this.norm(types);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const acts = this.state.activities;
    const stats = document.getElementById('arStats');
    stats.innerHTML = [
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total Activities</div><div class="fs-4 fw-bold">${this.esc(this.state.activities.length)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total Participants</div><div class="fs-4 fw-bold">${this.esc(this.state.participants.length)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Activity Types</div><div class="fs-4 fw-bold">${this.esc(this.state.types.length)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Upcoming</div><div class="fs-4 fw-bold text-primary">${this.esc(this.state.activities.filter(a => new Date(a.start_date || a.date) >= new Date()).length)}</div></div></div>`,
    ].join('');

    const body = document.getElementById('arBody');
    if (!this.state.activities.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No activities found.</td></tr>'; return; }
    body.innerHTML = this.state.activities.map(a => `<tr>
      <td class="small fw-semibold">${this.esc(a.name || `#${a.id}`)}</td>
      <td class="small text-muted">${this.esc(a.type_name || '—')}</td>
      <td class="small">${this.esc(a.start_date ? new Date(a.start_date).toLocaleDateString() : '—')}</td>
      <td class="small">${this.esc(a.end_date ? new Date(a.end_date).toLocaleDateString() : '—')}</td>
      <td class="small text-center">${this.esc(a.participant_count || 0)}</td>
      <td class="small"><span class="badge bg-${a.status === 'active' ? 'success' : a.status === 'completed' ? 'secondary' : 'warning'}">${this.esc(a.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="activityReportsController.viewDetails(${a.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "activity-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Activity Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("activity_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "activities"]);
  },
};

window.activityReportsController = activityReportsController;
document.addEventListener('DOMContentLoaded', () => activityReportsController.init().catch(() => {}));