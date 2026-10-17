const lowStockAlertsController = {
  state: { items: [], count: 0, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.items || r?.data?.items || r?.data || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "low-stock-alerts-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Low Stock Alerts Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('lsaBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const r = cache?.items !== undefined ? cache.items : await this.API('GET', '/inventory/items-low-stock');
      const payload = r?.data ?? r ?? {};
      const items = Array.isArray(payload) ? payload : (Array.isArray(payload?.items) ? payload.items : []);
      this.state.items = items;
      this.state.count = Number(payload?.count ?? items.length);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const q = (document.getElementById('lsaSearch')?.value || '').toLowerCase();
    const items = q ? this.state.items.filter(i => (i.name || i.item_name || '').toLowerCase().includes(q)) : this.state.items;

    const stats = document.getElementById('lsaStats');
    const total = items.length;
    const outOfStock = items.filter(i => Number(i.quantity_on_hand ?? i.current_quantity ?? 0) <= 0).length;
    stats.innerHTML = [
      `<div class="col-md-4"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Items below reorder level</div><div class="fs-4 fw-bold text-danger">${this.esc(total)}</div></div></div>`,
      `<div class="col-md-4"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Out of stock</div><div class="fs-4 fw-bold text-warning">${this.esc(outOfStock)}</div></div></div>`,
      `<div class="col-md-4"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total shortage units</div><div class="fs-4 fw-bold">${this.esc(items.reduce((s, i) => s + Math.max(0, Number(i.shortage_quantity ?? 0)), 0))}</div></div></div>`,
    ].join('');

    const body = document.getElementById('lsaBody');
    if (!items.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No low-stock items found.</td></tr>'; return; }
    body.innerHTML = items.map(i => {
      const onHand = Number(i.quantity_on_hand ?? i.current_quantity ?? 0);
      const reorder = Number(i.reorder_level ?? i.minimum_quantity ?? 0);
      const shortage = Number(i.shortage_quantity ?? reorder - onHand);
      const cost = Number(i.unit_cost ?? 0);
      const badge = onHand <= 0 ? 'bg-danger' : (shortage > 0 ? 'bg-warning text-dark' : 'bg-secondary');
      return `<tr>
        <td class="small fw-semibold">${this.esc(i.name || i.item_name || `#${i.id}`)}<div class="text-muted fw-normal">${this.esc(i.code || '')}</div></td>
        <td class="small">${this.esc(i.category_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(i.location_name || i.location || '\u2014')}</td>
        <td><span class="badge ${badge}">${this.esc(onHand)}</span></td>
        <td class="small">${this.esc(reorder)}</td>
        <td class="small text-danger fw-semibold">${this.esc(Math.max(0, shortage))}</td>
        <td class="small text-muted">${this.esc(i.unit || '\u2014')}</td>
        <td class="small">KES ${cost.toLocaleString()}</td>
      </tr>`;
    }).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "low-stock-alerts-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Low Stock Alerts Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  setupEventListeners() {
    document.getElementById('lsaSearch')?.addEventListener('input', () => this.render());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    this.setupEventListeners();
    await this.load();
    window.APIRealtime?.register?.("low_stock_alerts", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["inventory"]);
  },
};

const lowStockAlertsControllerInstance = lowStockAlertsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => lowStockAlertsControllerInstance.init().catch(() => {}));
} else {
  lowStockAlertsControllerInstance.init().catch(() => {});
}

window.lowStockAlertsController = lowStockAlertsControllerInstance;