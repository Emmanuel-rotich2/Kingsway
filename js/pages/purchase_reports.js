/** Procurement summary from the canonical line-level purchase report. */
const purchaseReportsController = {
  state: { rows: [] },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  async load() {
    const statusHost = document.getElementById('prStatusBody');
    const supplierBody = document.getElementById('prSupBody');
    if (statusHost) statusHost.textContent = 'Loading purchase report…';
    if (supplierBody) supplierBody.innerHTML = '<tr><td colspan="3" class="text-center py-4">Loading…</td></tr>';
    try {
      const response = await window.callAPI('/api/inventory/purchase-report', 'GET');
      const payload = response?.data?.data ?? response?.data ?? response ?? {};
      this.state.rows = Array.isArray(payload.purchases) ? payload.purchases : [];
      this.render();
    } catch (error) {
      const message = this.esc(error?.message || 'Purchase report could not be loaded.');
      if (statusHost) statusHost.innerHTML = `<div class="text-danger" role="alert">${message}</div>`;
      if (supplierBody) supplierBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-4">${message}</td></tr>`;
      const stats = document.getElementById('prStats'); if (stats) stats.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${message}</div></div>`;
    }
  },
  render() {
    const rows = this.state.rows;
    const total = rows.reduce((sum, row) => sum + Number(row.total || 0), 0);
    const orders = new Set(rows.map((row) => row.order_no).filter(Boolean)).size;
    const stats = document.getElementById('prStats');
    if (stats) stats.innerHTML = [['Purchase lines', rows.length], ['Orders', orders], ['Total value', `KES ${total.toLocaleString()}`]].map(([label, value]) => `<div class="col-6 col-lg-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">${label}</div><strong class="fs-4">${this.esc(value)}</strong></div></div></div>`).join('');
    const statusTotals = new Map();
    const suppliers = new Map();
    rows.forEach((row) => {
      const status = String(row.status || 'unknown');
      statusTotals.set(status, (statusTotals.get(status) || 0) + 1);
      const name = String(row.supplier_name || 'Unspecified supplier');
      const current = suppliers.get(name) || { count: 0, value: 0 };
      current.count += 1; current.value += Number(row.total || 0); suppliers.set(name, current);
    });
    const statusHost = document.getElementById('prStatusBody');
    if (statusHost) statusHost.innerHTML = statusTotals.size ? [...statusTotals.entries()].map(([status, count]) => `<div class="d-flex justify-content-between border-bottom py-2"><span>${this.esc(status)}</span><strong>${count.toLocaleString()} line${count === 1 ? '' : 's'}</strong></div>`).join('') : '<p class="text-muted mb-0">No purchase data is recorded.</p>';
    const supplierBody = document.getElementById('prSupBody');
    const top = [...suppliers.entries()].map(([name, value]) => ({ name, ...value })).sort((a, b) => b.value - a.value).slice(0, 10);
    if (supplierBody) supplierBody.innerHTML = top.length ? top.map((supplier) => `<tr><td>${this.esc(supplier.name)}</td><td>${supplier.count.toLocaleString()}</td><td>KES ${supplier.value.toLocaleString()}</td></tr>`).join('') : '<tr><td colspan="3" class="text-center text-muted py-4">No supplier purchases found.</td></tr>';
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('inventory')) return window.showNotification?.('You do not have permission to export purchasing data.', 'error');
    if (!this.state.rows.length) return window.showNotification?.('There is no purchase data to export.', 'info');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const data = [['Order', 'Supplier', 'Item', 'Quantity', 'Unit Price', 'Total', 'Order Date', 'Status'], ...this.state.rows.map((row) => [row.order_no, row.supplier_name, row.item_name, row.quantity, row.unit_price, row.total, row.order_date, row.status])];
    window.KingswayFileLifecycle?.exportText?.(data.map((row) => row.map(quote).join(',')).join('\r\n'), 'purchase-reports.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    if (window.AuthContext?.ready) window.AuthContext.ready().then(() => this.load()); else this.load();
    document.getElementById('prExportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('prPrintBtn')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('inventory')) window.print(); });
    window.APIRealtime?.register?.('purchase_reports', { refresh: () => this.load() }, ['inventory', 'reports']);
  },
};
window.purchaseReportsController = purchaseReportsController;
document.addEventListener('DOMContentLoaded', () => purchaseReportsController.init());
