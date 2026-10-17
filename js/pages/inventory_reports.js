/**
 * inventory_reports.js — Inventory Reports Controller (standalone page).
 * Stock levels, requisition status summary, adjustment logs.
 */
const inventoryReportsController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  state: { referenceCache: null },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  fmtDateTime(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return `${dt.toLocaleDateString()} ${dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.items || r || []); },

  badgeClass(s) {
    return { approved: 'bg-success', pending: 'bg-warning text-dark', rejected: 'bg-danger', draft: 'bg-secondary', fulfilled: 'bg-primary', cancelled: 'bg-secondary' }[s] || 'bg-secondary';
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("inventory", "inventory-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Inventory Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const stockBody = document.getElementById('irStockBody');
    const adjBody = document.getElementById('irAdjBody');
    stockBody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    adjBody.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    document.getElementById('irReqSummary').innerHTML = '<div class="spinner-border spinner-border-sm"></div>';

    try {
      const cache = this.state.referenceCache;
      const [stock, reqs, adjustments] = await Promise.all([
        cache?.stock !== undefined ? cache.stock : this.API('GET', '/reports/inventory-stock-levels').catch(() => []),
        cache?.requisitions !== undefined ? cache.requisitions : this.API('GET', '/reports/requisitions-summary').catch(() => []),
        cache?.adjustments !== undefined ? cache.adjustments : this.API('GET', '/reports/inventory-adjustment-logs').catch(() => []),
      ]);

      const stockList = this.norm(stock);
      const reqList = this.norm(reqs);
      const adjList = this.norm(adjustments);

      if (!stockList.length) { stockBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No stock data.</td></tr>'; }
      else {
        stockBody.innerHTML = stockList.map(i => `<tr>
          <td class="small fw-semibold">${this.esc(i.name || `#${i.id}`)}</td>
          <td class="small text-muted">${this.esc(i.category || '\u2014')}</td>
          <td class="small">${this.esc(i.current_quantity ?? 0)}</td>
          <td class="small text-muted">${this.esc(i.unit || '\u2014')}</td>
        </tr>`).join('');
      }

      if (!reqList.length) { document.getElementById('irReqSummary').innerHTML = '<span class="text-muted">No requisitions recorded.</span>'; }
      else {
        document.getElementById('irReqSummary').innerHTML = `<div class="row g-2">
          ${reqList.map(r => `<div class="col-sm-6 col-md-4"><span class="badge ${this.badgeClass(r.status)}">${this.esc(r.status || 'unknown')}</span> <span class="fw-semibold">${this.esc(r.total)}</span></div>`).join('')}
        </div>`;
      }

      if (!adjList.length) { adjBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No adjustment logs.</td></tr>'; }
      else {
        adjBody.innerHTML = adjList.map(a => `<tr>
          <td class="small">${this.fmtDateTime(a.adjusted_at)}</td>
          <td class="small fw-semibold">${this.esc(a.item_name || `#${a.item_id}`)}</td>
          <td class="small">${this.esc(a.quantity ?? 0)}</td>
          <td class="small">KES ${Number(a.unit_cost ?? 0).toLocaleString()}</td>
          <td class="small text-muted">${this.esc(a.notes || '\u2014')}</td>
        </tr>`).join('');
      }
    } catch (e) {
      stockBody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("inventory_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["inventory", "finance"]);
  },
};

window.inventoryReportsController = inventoryReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => inventoryReportsController.init().catch(() => {}));
} else {
  inventoryReportsController.init().catch(() => {});
}
