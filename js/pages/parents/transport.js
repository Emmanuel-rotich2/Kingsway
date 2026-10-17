/**
 * Parent Transport Controller
 * Parent-facing view of their children's transport arrangements.
 */
const parentTransportController = {
  state: { referenceCache: null, children: [], routes: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("transport", "transport-dashboard-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Parent Transport Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [children, routes] = await Promise.all([
        cache?.children !== undefined ? cache.children : this.API('GET', '/api/parent/my-children-transport'),
        cache?.routes !== undefined ? cache.routes : this.API('GET', '/api/transport/all-routes'),
      ]);
      this.state.children = Array.isArray(children?.data) ? children.data : (Array.isArray(children) ? children : []);
      this.state.routes = Array.isArray(routes?.data) ? routes.data : (Array.isArray(routes) ? routes : []);
      this.renderChildren();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderChildren() {
    const children = this.state.children;
    const container = document.getElementById('ptChildrenContainer');
    if (!children.length) { container.innerHTML = '<p class="text-center text-muted py-4">No children with transport assigned.</p>'; return; }
    container.innerHTML = children.map(c => `<div class="card mb-3"><div class="card-body">
      <h6 class="card-title">${this.esc(c.name)}</h6>
      <p class="card-text small mb-1"><strong>Route:</strong> ${this.esc(c.route_name || 'Not assigned')}</p>
      <p class="card-text small mb-1"><strong>Stop:</strong> ${this.esc(c.stop_name || '—')}</p>
      <p class="card-text small mb-1"><strong>Pickup:</strong> ${this.fmtTime(c.pickup_time)} | <strong>Drop-off:</strong> ${this.fmtTime(c.dropoff_time)}</p>
      <p class="card-text small"><strong>Vehicle:</strong> ${this.esc(c.vehicle_reg || '—')} | <strong>Driver:</strong> ${this.esc(c.driver_name || '—')}</p>
    </div></div>`).join('');
  },

  fmtTime(v) { if (!v) return '—'; return v.length > 5 ? v.substring(0, 5) : v; },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("parent_transport", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["transport", "parent"]);
  },
};

window.parentTransportController = parentTransportController;
document.addEventListener('DOMContentLoaded', () => parentTransportController.init().catch(() => {}));