const mealStatisticsController = {
  state: { stats: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "meal-statistics-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Meal Statistics Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const valBody = document.getElementById('msValBody');
    valBody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const r = cache?.stats !== undefined ? cache.stats : await this.API('GET', '/inventory/items-stock-valuation');
      const valPayload = r?.data ?? r ?? {};
      const byCategory = Array.isArray(valPayload?.by_category) ? valPayload.by_category : [];
      const grandTotal = Number(valPayload?.grand_total ?? 0);

      document.getElementById('msGrandTotal').textContent = `KES ${grandTotal.toLocaleString()}`;
      document.getElementById('msCategoryCount').textContent = byCategory.length;

      if (!byCategory.length) { valBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No valuation data.</td></tr>'; }
      else {
        valBody.innerHTML = byCategory.map(c => `<tr>
          <td class="small fw-semibold">${this.esc(c.category_name || '\u2014')}</td>
          <td class="small">${this.esc(c.item_count ?? 0)}</td>
          <td class="small">${this.esc(Number(c.total_quantity ?? 0).toLocaleString())}</td>
          <td class="small">KES ${this.esc(Number(c.total_value ?? 0).toLocaleString())}</td>
        </tr>`).join('');
      }
    } catch (e) { valBody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "meal-statistics-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Meal Statistics Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("meal_statistics", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["catering", "reports"]);
  },
};

const mealStatisticsControllerInstance = mealStatisticsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => mealStatisticsControllerInstance.init().catch(() => {}));
} else {
  mealStatisticsControllerInstance.init().catch(() => {});
}

window.mealStatisticsController = mealStatisticsControllerInstance;