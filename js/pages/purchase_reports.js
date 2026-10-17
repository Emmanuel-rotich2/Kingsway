/**
 * Purchase Reports Controller
 * Procurement, supplier orders, and purchasing analytics.
 */
const purchaseReportsController = {
  state: { suppliers: [], items: [], categories: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "purchase-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Purchase Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [suppliers, items, cats] = await Promise.all([
        cache?.suppliers !== undefined ? cache.suppliers : this.API('GET', '/api/inventory/suppliers-list'),
        cache?.items !== undefined ? cache.items : this.API('GET', '/api/inventory/items-list'),
        cache?.categories !== undefined ? cache.categories : this.API('GET', '/api/inventory/categories-list'),
      ]);
      this.state.suppliers = this.norm(suppliers);
      this.state.items = this.norm(items);
      this.state.categories = this.norm(cats);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const s = document.getElementById('puSupplier');
    const i = document.getElementById('puItem');
    const c = document.getElementById('puCategory');
    s.innerHTML = '<option value="">All Suppliers</option>' + this.state.suppliers.map(x => `<option value="${x.id}">${this.esc(x.name || x.supplier_name)}</option>`).join('');
    i.innerHTML = '<option value="">All Items</option>' + this.state.items.map(x => `<option value="${x.id}">${this.esc(x.name || x.item_name)}</option>`).join('');
    c.innerHTML = '<option value="">All Categories</option>' + this.state.categories.map(x => `<option value="${x.id}">${this.esc(x.name || x.category)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('puSupplier')?.value) params.set('supplier_id', document.getElementById('puSupplier').value);
    if (document.getElementById('puItem')?.value) params.set('item_id', document.getElementById('puItem').value);
    if (document.getElementById('puCategory')?.value) params.set('category_id', document.getElementById('puCategory').value);

    try {
      const response = await this.API('GET', '/api/reports/purchases', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('puBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No purchase data.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.order_no || '—')}</td>
      <td class="small">${this.esc(x.supplier_name || '—')}</td>
      <td class="small">${this.esc(x.item_name || '—')}</td>
      <td class="small">${this.esc(x.quantity || 0)}</td>
      <td class="small">KES ${Number(x.unit_price || 0).toLocaleString()}</td>
      <td class="small fw-semibold">KES ${Number(x.total || 0).toLocaleString()}</td>
      <td class="small">${this.esc(x.order_date || '—')}</td>
      <td class="small"><span class="badge bg-${x.status === 'delivered' ? 'success' : x.status === 'pending' ? 'warning' : 'danger'}">${this.esc(x.status || '—')}</span></td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "purchase-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Purchase Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("purchase_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
      }
    }, ["reports", "inventory"]);
  },
};

window.purchaseReportsController = purchaseReportsController;
document.addEventListener('DOMContentLoaded', () => purchaseReportsController.init().catch(() => {}));