const menuPlanningController = {
  state: { menus: [], referenceCache: null },

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
      const response = await window.API.nodeViews.read("catering", "menu-planning-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Menu Planning Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('mpBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const r = cache?.menus !== undefined ? cache.menus : await this.API('GET', '/catering/menu');
      this.state.menus = this.norm(r);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const menus = this.state.menus;
    const body = document.getElementById('mpBody');
    if (!menus.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No menus planned.</td></tr>'; return; }
    body.innerHTML = menus.map(m => `<tr>
      <td class="small">${this.fmtDate(m.menu_date)}</td>
      <td class="small fw-semibold">${this.esc(m.menu_name || 'Menu')}</td>
      <td class="small">${this.esc(m.meal_type || '\u2014')}</td>
      <td class="small text-muted">${this.esc(m.category_name || '\u2014')}</td>
      <td class="small">${this.esc(m.preparation_status || 'pending')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="menuPlanningController.viewMenu(${m.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.menus || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "menu-planning-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Menu Planning Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("menu_planning", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["catering"]);
  },
};

const menuPlanningControllerInstance = menuPlanningController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => menuPlanningControllerInstance.init().catch(() => {}));
} else {
  menuPlanningControllerInstance.init().catch(() => {});
}

window.menuPlanningController = menuPlanningControllerInstance;