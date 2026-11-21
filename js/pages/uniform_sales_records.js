/**
 * uniform_sales_records.js — Uniform Sales Records Controller (standalone page).
 * Read-only list of uniform item sales with payment status.
 */
const uniformSalesRecordsController = {
  state: { sales: [], page: 1, pages: 0, total: 0, limit: 20 },

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
    return { paid: 'bg-success', completed: 'bg-success', pending: 'bg-warning text-dark', partially_paid: 'bg-info text-dark', cancelled: 'bg-secondary' }[s] || 'bg-secondary';
  },

  getParams(page) {
    const params = { page, limit: this.state.limit };
    const status = document.getElementById('usrStatus')?.value;
    const from = document.getElementById('usrFrom')?.value;
    const to = document.getElementById('usrTo')?.value;
    if (status) params.payment_status = status;
    if (from) params.date_from = from;
    if (to) params.date_to = to;
    return params;
  },

  async load(page = 1) {
    const body = document.getElementById('usrBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/inventory/uniform-sales-list', null, this.getParams(page));
      const payload = r?.data ?? r ?? {};
      this.state.sales = Array.isArray(payload?.sales) ? payload.sales : (Array.isArray(payload) ? payload : []);
      this.state.page = Number(payload?.pagination?.page || page);
      this.state.pages = Number(payload?.pagination?.total_pages || 0);
      this.state.total = Number(payload?.pagination?.total || this.state.sales.length);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('usrBody');
    if (!this.state.sales.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No uniform sales found.</td></tr>'; }
    else {
      body.innerHTML = this.state.sales.map(s => `<tr>
        <td class="small">${this.fmtDate(s.sale_date)}</td>
        <td class="small">${this.esc(s.admission_number || '\u2014')}</td>
        <td class="small fw-semibold">${this.esc(s.student_name || '\u2014')}</td>
        <td class="small">${this.esc(s.item_name || '\u2014')} <span class="text-muted">(${this.esc(s.item_code || '')})</span></td>
        <td class="small text-muted">${this.esc(s.size || '\u2014')}</td>
        <td class="small">${this.esc(s.quantity ?? 0)}</td>
        <td class="small fw-semibold">KES ${Number(s.total_amount ?? 0).toLocaleString()}</td>
        <td><span class="badge ${this.badgeClass(s.payment_status)}">${this.esc(s.payment_status || 'pending')}</span></td>
        <td class="small text-muted">${this.esc(s.sold_by_name || '\u2014')}</td>
      </tr>`).join('');
    }
    document.getElementById('usrInfo').textContent = `Showing ${this.state.sales.length} of ${this.state.total} sales`;
    const pages = document.getElementById('usrPages');
    let html = '';
    if (this.state.page > 1) html += `<button class="btn btn-outline-secondary" data-kw-onclick="uniformSalesRecordsController.load(${this.state.page - 1})">&laquo; Prev</button>`;
    html += `<span class="btn btn-light disabled">Page ${this.state.page}${this.state.pages ? ` / ${this.state.pages}` : ''}</span>`;
    if (this.state.page < this.state.pages) html += `<button class="btn btn-outline-secondary" data-kw-onclick="uniformSalesRecordsController.load(${this.state.page + 1})">Next &raquo;</button>`;
    pages.innerHTML = html;
  },

  setupEventListeners() {
    ['usrStatus', 'usrFrom', 'usrTo'].forEach(id => document.getElementById(id)?.addEventListener('change', () => this.load()));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.uniformSalesRecordsController = uniformSalesRecordsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => uniformSalesRecordsController.init().catch(() => {}));
} else {
  uniformSalesRecordsController.init().catch(() => {});
}
