/**
 * stock_reports.js — Stock Reports Controller (standalone page).
 * Inventory valuation by category + item usage rates.
 */
const stockReportsController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  state: { referenceCache: null },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  monthName(m) {
    return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][Number(m) - 1] || this.esc(m);
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "stock-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Stock Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const valBody = document.getElementById('srValBody');
    const usageBody = document.getElementById('srUsageBody');
    valBody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    usageBody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const [valRes, usageRes] = await Promise.all([
        cache?.valuation !== undefined ? cache.valuation : this.API('GET', '/inventory/items-stock-valuation').catch(() => null),
        cache?.usage !== undefined ? cache.usage : this.API('GET', '/reports/inventory-usage-rates').catch(() => null),
      ]);

      const valPayload = valRes?.data ?? valRes ?? {};
      const byCategory = Array.isArray(valPayload?.by_category) ? valPayload.by_category : [];
      const grandTotal = Number(valPayload?.grand_total ?? 0);

      const usage = this.norm(usageRes);

      document.getElementById('srGrandTotal').textContent = `KES ${grandTotal.toLocaleString()}`;
      document.getElementById('srCategoryCount').textContent = byCategory.length;
      document.getElementById('srTotalQty').textContent = byCategory.reduce((s, c) => s + Number(c.total_quantity ?? 0), 0).toLocaleString();

      if (!byCategory.length) { valBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No valuation data.</td></tr>'; }
      else {
        valBody.innerHTML = byCategory.map(c => `<tr>
          <td class="small fw-semibold">${this.esc(c.category_name || '\u2014')}</td>
          <td class="small">${this.esc(c.item_count ?? 0)}</td>
          <td class="small">${this.esc(Number(c.total_quantity ?? 0).toLocaleString())}</td>
          <td class="small">KES ${this.esc(Number(c.total_value ?? 0).toLocaleString())}</td>
        </tr>`).join('');
      }

      if (!usage.length) { usageBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No usage data.</td></tr>'; }
      else {
        usageBody.innerHTML = usage.slice(0, 100).map(u => `<tr>
          <td class="small">${this.esc(u.year)}</td>
          <td class="small">${this.monthName(u.month)}</td>
          <td class="small">${this.esc(u.item_id ?? '\u2014')}</td>
          <td class="small fw-semibold">${this.esc(Number(u.total_used ?? 0).toLocaleString())}</td>
        </tr>`).join('');
      }
    } catch (e) {
      valBody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "stock-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Stock Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("stockReports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["inventory", "reports"]);
  },
};

window.stockReportsController = stockReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => stockReportsController.init().catch(() => {}));
} else {
  stockReportsController.init().catch(() => {});
}
