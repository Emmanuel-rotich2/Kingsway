/**
 * food_orders.js — Food Orders Controller (standalone page).
 * Food/catering supplier purchase order tracking.
 */
const foodOrdersController = {
  state: { orders: [], page: 1, pages: 0, total: 0, limit: 20, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  badgeClass(s) {
    return { received: 'bg-success', approved: 'bg-primary', ordered: 'bg-info text-dark', pending: 'bg-warning text-dark', draft: 'bg-secondary', cancelled: 'bg-danger' }[s] || 'bg-secondary';
  },

  getParams(page) {
    const params = { page, limit: this.state.limit };
    const search = document.getElementById('foSearch')?.value.trim();
    const status = document.getElementById('foStatus')?.value;
    if (search) params.search = search;
    if (status) params.status = status;
    return params;
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "food-orders-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Food Orders Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(page = 1) {
    const body = document.getElementById('foBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const r = cache?.suppliers !== undefined ? cache.suppliers : await this.API('GET', '/inventory/purchase-orders-list', null, this.getParams(page));
      const payload = r?.data ?? r ?? {};
      this.state.orders = Array.isArray(payload?.orders) ? payload.orders : (Array.isArray(payload) ? payload : []);
      this.state.page = Number(payload?.pagination?.page || page);
      this.state.pages = Number(payload?.pagination?.total_pages || 0);
      this.state.total = Number(payload?.pagination?.total || this.state.orders.length);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('foBody');
    if (!this.state.orders.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No food orders found.</td></tr>'; }
    else {
      body.innerHTML = this.state.orders.map(o => `<tr>
        <td class="small fw-semibold">${this.esc(o.order_number || `#${o.id}`)}</td>
        <td class="small">${this.esc(o.supplier_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(o.contact_person || '\u2014')}</td>
        <td class="small">${this.fmtDate(o.order_date)}</td>
        <td class="small">${this.fmtDate(o.expected_delivery_date || o.expected_date)}</td>
        <td class="small">${this.esc(o.item_count ?? '\u2014')}</td>
        <td class="small fw-semibold">KES ${Number(o.total_amount ?? 0).toLocaleString()}</td>
        <td><span class="badge ${this.badgeClass(o.status)}">${this.esc(o.status || 'pending')}</span></td>
      </tr>`).join('');
    }
    document.getElementById('foInfo').textContent = `Showing ${this.state.orders.length} of ${this.state.total} orders`;
    const pages = document.getElementById('foPages');
    let html = '';
    if (this.state.page > 1) html += `<button class="btn btn-outline-secondary" onclick="foodOrdersController.load(${this.state.page - 1})">&laquo; Prev</button>`;
    html += `<span class="btn btn-light disabled">Page ${this.state.page}${this.state.pages ? ` / ${this.state.pages}` : ''}</span>`;
    if (this.state.page < this.state.pages) html += `<button class="btn btn-outline-secondary" onclick="foodOrdersController.load(${this.state.page + 1})">Next &raquo;</button>`;
    pages.innerHTML = html;
  },

  setupEventListeners() {
    let timer = null;
    document.getElementById('foSearch')?.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => this.load(), 400);
    });
    document.getElementById('foStatus')?.addEventListener('change', () => this.load());
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "food-orders-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Food Orders Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("food_orders", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["inventory", "finance"]);
  },
};

const foodOrdersControllerInstance = foodOrdersController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => foodOrdersControllerInstance.init().catch(() => {}));
} else {
  foodOrdersControllerInstance.init().catch(() => {});
}

window.foodOrdersController = foodOrdersControllerInstance;