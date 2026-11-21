/**
 * goods_received.js — Goods Received Controller (standalone page).
 * Tracks purchase orders and confirms delivery.
 */
const goodsReceivedController = {
  state: { orders: [], page: 1, pages: 0, total: 0, limit: 20 },

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

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

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
    const status = document.getElementById('grStatus')?.value;
    if (status) params.status = status;
    return params;
  },

  async load(page = 1) {
    const body = document.getElementById('grBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/inventory/purchase-orders-list', null, this.getParams(page));
      const payload = r?.data ?? r ?? {};
      this.state.orders = Array.isArray(payload?.orders) ? payload.orders : (Array.isArray(payload) ? payload : []);
      this.state.page = Number(payload?.pagination?.page || page);
      this.state.pages = Number(payload?.pagination?.total_pages || 0);
      this.state.total = Number(payload?.pagination?.total || this.state.orders.length);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  async receive(poId) {
    if (!confirm('Confirm that this order has been received in full?')) return;
    try {
      await this.API('POST', '/inventory/purchase-orders-receive', { id: poId });
      this.notify('Purchase order marked as received.', 'success');
      this.load();
    } catch (e) {
      this.notify(e.message || 'Failed to mark order as received.', 'danger');
    }
  },

  render() {
    const body = document.getElementById('grBody');
    if (!this.state.orders.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No purchase orders found.</td></tr>'; }
    else {
      body.innerHTML = this.state.orders.map(o => {
        const received = (o.status || '').toLowerCase() === 'received' || (o.status || '').toLowerCase() === 'cancelled';
        const total = Number(o.total_amount ?? 0);
        return `<tr>
          <td class="small fw-semibold">${this.esc(o.order_number || `#${o.id}`)}</td>
          <td class="small">${this.esc(o.supplier_name || '\u2014')}</td>
          <td class="small">${this.fmtDate(o.order_date)}</td>
          <td class="small">${this.fmtDate(o.expected_delivery_date || o.expected_date)}</td>
          <td class="small">${this.esc(o.item_count ?? '\u2014')}</td>
          <td class="small fw-semibold">KES ${total.toLocaleString()}</td>
          <td><span class="badge ${this.badgeClass(o.status)}">${this.esc(o.status || 'pending')}</span></td>
          <td class="text-end">${received ? '<span class="text-muted small">\u2014</span>' : `<button class="btn btn-sm btn-outline-success" data-kw-onclick="goodsReceivedController.receive(${o.id})">Mark Received</button>`}</td>
        </tr>`;
      }).join('');
    }
    document.getElementById('grInfo').textContent = `Showing ${this.state.orders.length} of ${this.state.total} orders`;
    const pages = document.getElementById('grPages');
    let html = '';
    if (this.state.page > 1) html += `<button class="btn btn-outline-secondary" data-kw-onclick="goodsReceivedController.load(${this.state.page - 1})">&laquo; Prev</button>`;
    html += `<span class="btn btn-light disabled">Page ${this.state.page}${this.state.pages ? ` / ${this.state.pages}` : ''}</span>`;
    if (this.state.page < this.state.pages) html += `<button class="btn btn-outline-secondary" data-kw-onclick="goodsReceivedController.load(${this.state.page + 1})">Next &raquo;</button>`;
    pages.innerHTML = html;
  },

  setupEventListeners() {
    document.getElementById('grStatus')?.addEventListener('change', () => this.load());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.goodsReceivedController = goodsReceivedController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => goodsReceivedController.init().catch(() => {}));
} else {
  goodsReceivedController.init().catch(() => {});
}
