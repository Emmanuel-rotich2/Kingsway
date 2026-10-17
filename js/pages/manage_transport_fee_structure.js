/**
 * manage_transport_fee_structure.js — Transport Payments Controller (standalone page).
 * Track transport bill payments, print receipts. Pricing is set by Director.
 */
const manageTransportFeeStructureController = {
  state: { bills: [], routes: [], page: 1, limit: 20, total: 0, currentBill: null },

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

  formatCurrency(n) { return 'KES ' + parseFloat(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

  badgeClass(s) { return { paid: 'bg-success', partial: 'bg-warning text-dark', pending: 'bg-danger' }[s] || 'bg-secondary'; },

  async loadReference() {
    try {
      const resp = await this.API('GET', 'transport/bills-summary', null, { billing_month: '' });
      const summary = resp?.summary || resp?.data?.summary || {};
      const routes = resp?.by_route || resp?.data?.by_route || [];
      this.state.routes = routes;
      const monthSel = document.getElementById('mtfMonth');
      const months = new Set();
      if (summary.billing_month) months.add(summary.billing_month);
      this.state.routes.forEach(r => { if (r.billing_month) months.add(r.billing_month); });
      const sortedMonths = Array.from(months).sort().reverse();
      monthSel.innerHTML = '<option value="">All months</option>' + sortedMonths.map(m => `<option value="${this.esc(m)}">${this.esc(m)}</option>`).join('');
      const routeSel = document.getElementById('mtfRoute');
      const uniqueRoutes = [...new Map(this.state.routes.map(r => [r.route_name, r])).values()];
      routeSel.innerHTML = '<option value="">All routes</option>' + uniqueRoutes.map(r => `<option value="${this.esc(r.route_name)}">${this.esc(r.route_name)}</option>`).join('');
    } catch (e) { /* silently fail — will retry on loadBills */ }
  },

  async loadBills(page = 1) {
    this.state.page = page;
    const body = document.getElementById('mtfBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const params = { page, limit: this.state.limit };
      const month = document.getElementById('mtfMonth')?.value;
      if (month) params.billing_month = month;
      const status = document.getElementById('mtfStatus')?.value;
      if (status) params.payment_status = status;
      const r = await this.API('GET', 'transport/bills', null, params);
      const data = r?.data || r || {};
      this.state.bills = Array.isArray(data.bills) ? data.bills : (Array.isArray(data) ? data : []);
      this.state.total = data.pagination?.total || this.state.bills.length;
      this.updateStats();
      this.render();
      this.renderPagination(data.pagination || {});
    } catch (e) {
      body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${this.esc(e.message || 'Failed to load transport bills')}</td></tr>`;
    }
  },

  updateStats() {
    let totalDue = 0, totalPaid = 0, outstanding = 0, paidCount = 0;
    this.state.bills.forEach(b => {
      const due = parseFloat(b.amount_due || 0);
      const paid = parseFloat(b.amount_paid || 0);
      totalDue += due;
      totalPaid += paid;
      outstanding += Math.max(0, due - paid);
      if (b.payment_status === 'paid') paidCount++;
    });
    document.getElementById('mtfTotalBills').textContent = this.state.total;
    document.getElementById('mtfTotalCollected').textContent = this.formatCurrency(totalPaid);
    document.getElementById('mtfOutstanding').textContent = this.formatCurrency(outstanding);
    document.getElementById('mtfPaidCount').textContent = paidCount;
  },

  render() {
    const body = document.getElementById('mtfBody');
    if (!this.state.bills.length) {
      body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No transport bills found.</td></tr>';
      return;
    }
    body.innerHTML = this.state.bills.map(b => {
      const balance = Math.max(0, parseFloat(b.amount_due || 0) - parseFloat(b.amount_paid || 0));
      return `
        <tr>
          <td class="small fw-semibold">${this.esc((b.first_name || '') + ' ' + (b.last_name || ''))}</td>
          <td class="small">${this.esc(b.admission_no || '\u2014')}</td>
          <td class="small">${this.esc(b.route_name || b.route_code || '\u2014')}</td>
          <td class="small">${this.esc(b.billing_month || '\u2014')}</td>
          <td class="small text-end">${this.formatCurrency(b.amount_due)}</td>
          <td class="small text-end text-success">${this.formatCurrency(b.amount_paid)}</td>
          <td class="small text-end text-danger">${this.formatCurrency(balance)}</td>
          <td><span class="badge ${this.badgeClass(b.payment_status)}">${this.esc(b.payment_status || 'pending')}</span></td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="manageTransportFeeStructureController.viewReceipt(${b.id})" title="View Receipt">
                <i class="bi bi-receipt"></i>
              </button>
              <button class="btn btn-outline-success" onclick="manageTransportFeeStructureController.printReceiptDirect(${b.id})" title="Print Receipt">
                <i class="bi bi-printer"></i>
              </button>
            </div>
          </td>
        </tr>`;
    }).join('');
  },

  renderPagination(pagination) {
    const container = document.getElementById('mtfPagControls');
    const info = document.getElementById('mtfPagInfo');
    if (!container || !info) return;
    const current = parseInt(pagination.page) || this.state.page || 1;
    const totalPages = parseInt(pagination.pages) || Math.ceil(this.state.total / this.state.limit) || 1;
    const total = parseInt(pagination.total) || this.state.total;
    const start = total === 0 ? 0 : (current - 1) * this.state.limit + 1;
    const end = Math.min(current * this.state.limit, total);
    info.textContent = `Showing ${start}-${end} of ${total}`;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    let html = `<button class="btn btn-sm btn-outline-primary" ${current === 1 ? 'disabled' : ''} onclick="manageTransportFeeStructureController.loadBills(${current - 1})">Prev</button>`;
    for (let i = Math.max(1, current - 2); i <= Math.min(totalPages, current + 2); i++) {
      html += `<button class="btn btn-sm ${i === current ? 'btn-primary' : 'btn-outline-primary'}" onclick="manageTransportFeeStructureController.loadBills(${i})">${i}</button>`;
    }
    html += `<button class="btn btn-sm btn-outline-primary" ${current === totalPages ? 'disabled' : ''} onclick="manageTransportFeeStructureController.loadBills(${current + 1})">Next</button>`;
    container.innerHTML = html;
  },

  viewReceipt(billId) {
    const bill = this.state.bills.find(b => b.id === billId);
    if (!bill) return this.notify('Bill not found', 'danger');
    this.state.currentBill = bill;
    const balance = Math.max(0, parseFloat(bill.amount_due || 0) - parseFloat(bill.amount_paid || 0));
    document.getElementById('mtfReceiptBody').innerHTML = `
      <div class="text-center mb-3">
        <h5 class="fw-bold">Kingsway Preparatory School</h5>
        <p class="text-muted small mb-0">Transport Payment Receipt</p>
      </div>
      <table class="table table-sm">
        <tr><td class="fw-semibold" style="width:40%">Student</td><td>${this.esc((bill.first_name || '') + ' ' + (bill.last_name || ''))}</td></tr>
        <tr><td class="fw-semibold">Admission No</td><td>${this.esc(bill.admission_no || '\u2014')}</td></tr>
        <tr><td class="fw-semibold">Route</td><td>${this.esc(bill.route_name || bill.route_code || '\u2014')}</td></tr>
        <tr><td class="fw-semibold">Billing Month</td><td>${this.esc(bill.billing_month || '\u2014')}</td></tr>
        <tr><td class="fw-semibold">Amount Due</td><td>${this.formatCurrency(bill.amount_due)}</td></tr>
        <tr><td class="fw-semibold">Amount Paid</td><td class="text-success">${this.formatCurrency(bill.amount_paid)}</td></tr>
        <tr><td class="fw-semibold">Balance</td><td class="text-danger">${this.formatCurrency(balance)}</td></tr>
        <tr><td class="fw-semibold">Status</td><td><span class="badge ${this.badgeClass(bill.payment_status)}">${this.esc(bill.payment_status || 'pending')}</span></td></tr>
      </table>`;
    new bootstrap.Modal(document.getElementById('mtfReceiptModal')).show();
  },

  printReceiptDirect(billId) {
    const bill = this.state.bills.find(b => b.id === billId);
    if (!bill) return this.notify('Bill not found', 'danger');
    this.state.currentBill = bill;
    this.doPrint();
  },

  printReceipt() {
    this.doPrint();
  },

  doPrint() {
    const bill = this.state.currentBill;
    if (!bill) return;
    const balance = Math.max(0, parseFloat(bill.amount_due || 0) - parseFloat(bill.amount_paid || 0));
    const w = window.open('', '_blank', 'width=400,height=600');
    w.document.write(`<html><head><title>Transport Receipt</title><style>
      body{font-family:monospace;font-size:12px;padding:20px;max-width:350px;margin:0 auto}
      h2,h3{margin:5px 0;text-align:center}table{width:100%;border-collapse:collapse}td{padding:3px 0}hr{border:1px dashed #000;margin:10px 0}
      .label{font-weight:bold;width:40%}.amount{text-align:right}
    </style></head><body>
      <h3>KINGSWAY PREPARATORY SCHOOL</h3>
      <p style="text-align:center;margin:2px 0">Transport Payment Receipt</p><hr>
      <table>
        <tr><td class="label">Student:</td><td>${this.esc((bill.first_name||'')+' '+(bill.last_name||''))}</td></tr>
        <tr><td class="label">Admission No:</td><td>${this.esc(bill.admission_no||'')}</td></tr>
        <tr><td class="label">Route:</td><td>${this.esc(bill.route_name||bill.route_code||'')}</td></tr>
        <tr><td class="label">Month:</td><td>${this.esc(bill.billing_month||'')}</td></tr>
      </table><hr>
      <table>
        <tr><td class="label">Amount Due:</td><td class="amount">${this.formatCurrency(bill.amount_due)}</td></tr>
        <tr><td class="label">Paid:</td><td class="amount" style="color:green">${this.formatCurrency(bill.amount_paid)}</td></tr>
        <tr><td class="label">Balance:</td><td class="amount" style="color:red">${this.formatCurrency(balance)}</td></tr>
        <tr><td class="label">Status:</td><td class="amount">${this.esc(bill.payment_status||'pending').toUpperCase()}</td></tr>
      </table><hr>
      <p style="text-align:center;font-size:10px;margin-top:15px">Generated ${new Date().toLocaleString()}</p>
    </body></html>`);
    w.document.close();
    w.print();
  },

  setupEventListeners() {
    ['mtfMonth', 'mtfRoute', 'mtfStatus'].forEach(id => document.getElementById(id)?.addEventListener('change', () => this.loadBills(1)));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadReference();
    await this.loadBills();
    window.APIRealtime?.register?.("manage_transport_fee_structure", { refresh: () => this.loadBills() }, ["finance", "transport"]);
  },
};

window.manageTransportFeeStructureController = manageTransportFeeStructureController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageTransportFeeStructureController.init().catch(() => {}));
} else {
  manageTransportFeeStructureController.init().catch(() => {});
}
