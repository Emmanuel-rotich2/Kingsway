const foodLowStockController = {
  state: { items: [], referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.items || r || []); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  stockBadge(s) {
    const status = String(s || '').toUpperCase();
    if (status === 'OUT OF STOCK') return 'bg-danger';
    if (status === 'REORDER' || status === 'LOW STOCK') return 'bg-warning text-dark';
    return 'bg-success';
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "food-low-stock-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Food Low Stock Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('flsBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const r = cache?.items !== undefined ? cache.items : await this.API('GET', '/catering/food-stock', null, { low_stock: '1', limit: 200 });
      this.state.items = this.norm(r);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const items = this.state.items;
    const stats = document.getElementById('flsStats');
    const out = items.filter(i => String(i.stock_status || '').toUpperCase() === 'OUT OF STOCK').length;
    const reorder = items.filter(i => String(i.stock_status || '').toUpperCase() === 'REORDER').length;
    const low = items.filter(i => String(i.stock_status || '').toUpperCase() === 'LOW STOCK').length;
    const expiring = items.filter(i => i.expiry_status && String(i.expiry_status).toUpperCase() !== 'OK').length;
    stats.innerHTML = [
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Out of Stock</div><div class="fs-4 fw-bold text-danger">${this.esc(out)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Reorder</div><div class="fs-4 fw-bold text-warning">${this.esc(reorder)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Low Stock</div><div class="fs-4 fw-bold">${this.esc(low)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Expiring</div><div class="fs-4 fw-bold text-danger">${this.esc(expiring)}</div></div></div>`,
    ].join('');

    const body = document.getElementById('flsBody');
    if (!items.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No low-stock food items found.</td></tr>'; return; }
    body.innerHTML = items.map(i => {
      const expiryWarn = i.expiry_status && String(i.expiry_status).toUpperCase() !== 'OK';
      return `<tr>
        <td class="small fw-semibold">${this.esc(i.name || `#${i.id}`)}<div class="text-muted fw-normal">${this.esc(i.code || '')}</div></td>
        <td class="small text-muted">${this.esc(i.category || '\u2014')}</td>
        <td class="small text-muted">${this.esc(i.location || '\u2014')}</td>
        <td class="small fw-semibold">${this.esc(i.current_quantity ?? 0)}</td>
        <td class="small">${this.esc(i.minimum_quantity ?? 0)}</td>
        <td class="small">${this.esc(i.reorder_level ?? 0)}</td>
        <td><span class="badge ${this.stockBadge(i.stock_status)}">${this.esc(i.stock_status || '\u2014')}</span></td>
        <td class="small ${expiryWarn ? 'text-danger fw-semibold' : 'text-muted'}">${this.fmtDate(i.expiry_date)}${expiryWarn ? ` (${this.esc(i.expiry_status)})` : ''}</td>
        <td class="small">KES ${Number(i.inventory_value ?? 0).toLocaleString()}</td>
      </tr>`;
    }).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "food-low-stock-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Food Low Stock Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("food_low_stock", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["inventory", "catering"]);
  },
};

const foodLowStockControllerInstance = foodLowStockController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => foodLowStockControllerInstance.init().catch(() => {}));
} else {
  foodLowStockControllerInstance.init().catch(() => {});
}

window.foodLowStockController = foodLowStockControllerInstance;