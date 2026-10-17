/**
 * payment_records.js — Payment Records Controller (standalone page).
 * Read-only ledger of recorded fee payments.
 */
const paymentRecordsController = {
  state: { payments: [], page: 1, pages: 0, total: 0, limit: 20 },

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

  fmtDateTime(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return `${dt.toLocaleDateString()} ${dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
  },

  badgeClass(s) {
    return { paid: 'bg-success', completed: 'bg-success', pending: 'bg-warning text-dark', failed: 'bg-danger', void: 'bg-secondary', cancelled: 'bg-secondary' }[s] || 'bg-secondary';
  },

  getParams(page) {
    const params = { type: 'payments', page, limit: this.state.limit };
    const from = document.getElementById('prFrom').value;
    const to = document.getElementById('prTo').value;
    const method = document.getElementById('prMethod').value;
    if (from) params.date_from = `${from} 00:00:00`;
    if (to) params.date_to = `${to} 23:59:59`;
    if (method) params.payment_method = method;
    return params;
  },

  async load(page = 1) {
    const body = document.getElementById('prBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'finance', null, this.getParams(page));
      const payload = r?.data ?? r ?? {};
      this.state.payments = Array.isArray(payload?.payments) ? payload.payments : (Array.isArray(payload) ? payload : []);
      this.state.page = Number(payload?.pagination?.page || page);
      this.state.pages = Number(payload?.pagination?.pages || 0);
      this.state.total = Number(payload?.pagination?.total || this.state.payments.length);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('prBody');
    if (!this.state.payments.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No payment records found.</td></tr>'; }
    else {
      body.innerHTML = this.state.payments.map(p => {
        const name = p.student_name || `${p.first_name || ''} ${p.last_name || ''}`.trim();
        const amount = Number(p.amount_paid ?? p.amount ?? 0);
        return `<tr>
          <td class="small">${this.fmtDateTime(p.transaction_date || p.payment_date || p.created_at)}</td>
          <td class="small">${this.esc(p.receipt_no || '\u2014')}</td>
          <td class="small">${this.esc(p.admission_no || p.student_no || p.admission_number || '\u2014')}</td>
          <td class="small fw-semibold">${this.esc(name || '\u2014')}</td>
          <td class="small">KES ${amount.toLocaleString()}</td>
          <td class="small text-muted">${this.esc(p.payment_method || p.method || '\u2014')}</td>
          <td><span class="badge ${this.badgeClass(p.status)}">${this.esc(p.status || 'pending')}</span></td>
          <td class="small text-muted">${this.esc(p.reference_no || p.transaction_ref || p.transaction_code || p.reference || '\u2014')}</td>
        </tr>`;
      }).join('');
    }
    document.getElementById('prInfo').textContent = `Showing ${this.state.payments.length} of ${this.state.total} records`;
    const pages = document.getElementById('prPages');
    let html = '';
    if (this.state.page > 1) html += `<button class="btn btn-outline-secondary" onclick="paymentRecordsController.load(${this.state.page - 1})">&laquo; Prev</button>`;
    html += `<span class="btn btn-light disabled">Page ${this.state.page}${this.state.pages ? ` / ${this.state.pages}` : ''}</span>`;
    if (this.state.page < this.state.pages) html += `<button class="btn btn-outline-secondary" onclick="paymentRecordsController.load(${this.state.page + 1})">Next &raquo;</button>`;
    pages.innerHTML = html;
  },

  setupEventListeners() {
    ['prFrom', 'prTo', 'prMethod'].forEach(id => document.getElementById(id)?.addEventListener('change', () => this.load()));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
    window.APIRealtime?.register?.("payment_records", { refresh: () => this.load(this.state.page) }, ["finance", "payments"]);
  },
};

window.paymentRecordsController = paymentRecordsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => paymentRecordsController.init().catch(() => {}));
} else {
  paymentRecordsController.init().catch(() => {});
}
