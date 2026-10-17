const todaysMenuController = {
  state: { menu: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "todays-menu-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Today's Menu Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('tmBody');
    body.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const r = cache?.menu !== undefined ? cache.menu : await this.API('GET', '/catering/menu');
      const payload = r?.data ?? r ?? {};
      this.state.menu = Array.isArray(payload) ? payload[0] : (payload.menu || payload);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const m = this.state.menu;
    const body = document.getElementById('tmBody');
    if (!m) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No menu published for today.</td></tr>'; return; }
    body.innerHTML = `<tr>
      <td class="small fw-semibold">${this.esc(m.menu_name || 'Today\'s Menu')}</td>
      <td class="small">${this.esc(m.meal_type || '\u2014')}</td>
      <td class="small text-muted">${this.esc(m.category_name || '\u2014')}</td>
      <td class="small">${this.fmtDate(m.menu_date)}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="todaysMenuController.viewDetails(${m.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`;
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "todays-menu-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Today's Menu Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("todays_menu", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["catering"]);
  },
};

const todaysMenuControllerInstance = todaysMenuController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => todaysMenuControllerInstance.init().catch(() => {}));
} else {
  todaysMenuControllerInstance.init().catch(() => {});
}

window.todaysMenuController = todaysMenuControllerInstance;