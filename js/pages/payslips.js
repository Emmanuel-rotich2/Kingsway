/**
 * Payslips Controller
 * Shows the logged-in staff member's own payslip history.
 * API: /api/staff/payroll-*  and  /api/finance/payrolls-*
 */

const payslipsController = {

  _staffId:  null,
  _payslips: [],
  _currentSlipData: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    const user = AuthContext.getUser();
    this._staffId = user?.staff_id ?? user?.user_id ?? null;

    this._populateYearFilter();
    await this.load();
  },

  _populateYearFilter: function () {
    const sel  = document.getElementById('psYear');
    const year = new Date().getFullYear();
    for (let y = year; y >= year - 4; y--) {
      sel.add(new Option(y, y, y === year, y === year));
    }
  },

  load: async function () {
    const tbody = document.getElementById('psTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';

    const year = document.getElementById('psYear').value;
    try {
      const params = { year };
      if (this._staffId) params.staff_id = this._staffId;

      const r = await callAPI('/staff/payroll-list?' + new URLSearchParams(params).toString(), 'GET');
      this._payslips = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      // Update current month card with most recent payslip
      if (this._payslips.length) {
        const latest = this._payslips[0];
        this._updateCurrentCard(latest);
      }

      this._renderTable(tbody, this._payslips);
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-danger text-center py-4">Failed to load payslips.</td></tr>';
      console.warn('Payslips load failed:', e);
    }
  },

  _updateCurrentCard: function (slip) {
    const fmt  = n => n != null ? 'KES ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 }) : '—';
    const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };

    setEl('psCurrentMonth', slip.month_label ?? slip.payroll_period ?? slip.month ?? '—');
    setEl('psGross',        fmt(slip.gross_pay     ?? slip.gross_salary));
    setEl('psDeductions',   fmt(slip.total_deductions));
    setEl('psNet',          fmt(slip.net_pay        ?? slip.net_salary));
    setEl('psStatus',       (slip.status ?? '').toUpperCase() || 'ISSUED');
    this._currentSlipData = slip;
  },

  _renderTable: function (tbody, payslips) {
    if (!payslips.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No payslips found for this year.</td></tr>';
      return;
    }
    const fmt = n => n != null ? 'KES ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 }) : '—';
    tbody.innerHTML = payslips.map(s => `
      <tr>
        <td><strong>${this._esc(s.month_label ?? s.payroll_period ?? s.month ?? '—')}</strong></td>
        <td class="text-end">${fmt(s.gross_pay ?? s.gross_salary)}</td>
        <td class="text-end text-muted">${fmt(s.paye_tax ?? s.paye)}</td>
        <td class="text-end text-muted">${fmt(s.nhif)}</td>
        <td class="text-end text-muted">${fmt(s.nssf)}</td>
        <td class="text-end fw-semibold text-success">${fmt(s.net_pay ?? s.net_salary)}</td>
        <td><span class="badge bg-${s.status === 'paid' || s.status === 'disbursed' ? 'success' : s.status === 'pending' ? 'warning' : 'secondary'}">${s.status ?? 'issued'}</span></td>
        <td>
          <button class="btn btn-sm btn-outline-primary" onclick="payslipsController.viewSlip(${s.id ?? s.payroll_id ?? 'null'}, '${this._esc(s.month ?? '')}', ${s.year ?? new Date().getFullYear()})">
            <i class="bi bi-eye"></i>
          </button>
        </td>
      </tr>`).join('');
  },

  viewSlip: async function (id, month, year) {
    const modal = document.getElementById('payslipModal');
    const body  = document.getElementById('payslipModalBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    bootstrap.Modal.getOrCreateInstance(modal).show();

    try {
      let detail = null;

      // Try detailed payslip endpoint
      const params = {};
      if (id)           params.payroll_id = id;
      if (this._staffId) params.staff_id  = this._staffId;
      if (month)         params.month     = month;
      if (year)          params.year      = year;

      const r = await callAPI('/staff/payroll-detailed-payslip?' + new URLSearchParams(params).toString(), 'GET');
      detail = r?.data ?? r;

      if (detail) {
        body.innerHTML = this._renderSlipHtml(detail);
      } else {
        body.innerHTML = '<div class="alert alert-warning">Payslip detail not available.</div>';
      }
    } catch (e) {
      body.innerHTML = '<div class="alert alert-danger">Failed to load payslip detail.</div>';
    }
  },

  _renderSlipHtml: function (d) {
    const fmt  = n => n != null ? 'KES ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 }) : '—';
    const row  = (label, value, cls = '') => `<tr><td class="text-muted">${label}</td><td class="text-end ${cls}">${value}</td></tr>`;

    return `
      <div class="p-3">
        <div class="text-center mb-4 border-bottom pb-3">
          <h5 class="mb-0">Kingsway Preparatory School</h5>
          <div class="text-muted">${this._esc(d.month_label ?? d.payroll_period ?? '')}</div>
        </div>
        <div class="row mb-4">
          <div class="col-6">
            <div class="small text-muted">Employee</div>
            <div class="fw-semibold">${this._esc(d.employee_name ?? d.staff_name ?? '—')}</div>
          </div>
          <div class="col-6 text-end">
            <div class="small text-muted">Employee No.</div>
            <div class="fw-semibold">${this._esc(d.employee_no ?? d.staff_no ?? '—')}</div>
          </div>
          <div class="col-6 mt-2">
            <div class="small text-muted">Designation</div>
            <div>${this._esc(d.designation ?? d.position ?? '—')}</div>
          </div>
          <div class="col-6 text-end mt-2">
            <div class="small text-muted">Department</div>
            <div>${this._esc(d.department ?? '—')}</div>
          </div>
        </div>

        <table class="table table-sm">
          <thead class="table-light"><tr><th>Earnings</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
            ${row('Basic Salary', fmt(d.basic_salary))}
            ${(d.allowances ?? []).map(a => row(this._esc(a.name), fmt(a.amount))).join('')}
            <tr class="fw-bold"><td>Gross Pay</td><td class="text-end">${fmt(d.gross_pay ?? d.gross_salary)}</td></tr>
          </tbody>
        </table>

        <table class="table table-sm mt-3">
          <thead class="table-light"><tr><th>Deductions</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
            ${row('PAYE', fmt(d.paye_tax ?? d.paye), 'text-danger')}
            ${row('NHIF', fmt(d.nhif), 'text-danger')}
            ${row('NSSF', fmt(d.nssf), 'text-danger')}
            ${(d.deductions ?? []).map(x => row(this._esc(x.name), fmt(x.amount), 'text-danger')).join('')}
            <tr class="fw-bold text-danger"><td>Total Deductions</td><td class="text-end">${fmt(d.total_deductions)}</td></tr>
          </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center bg-primary text-white rounded p-3 mt-3">
          <span class="fs-5 fw-bold">NET PAY</span>
          <span class="fs-4 fw-bold">${fmt(d.net_pay ?? d.net_salary)}</span>
        </div>
      </div>`;
  },

  viewCurrentSlip: function () { this.viewSlip(null); },
  printCurrentSlip: function () { window.print(); },

  downloadP9: async function () {
    try {
      const year   = document.getElementById('psYear').value;
      const params = new URLSearchParams({ year });
      if (this._staffId) params.set('staff_id', this._staffId);
      const url = (window.APP_BASE || '') + '/api/staff/payroll-download-p9?' + params.toString();
      window.open(url, '_blank');
    } catch (e) { showNotification('P9 download failed', 'error'); }
  },

  _esc: function (str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  },
};

document.addEventListener('DOMContentLoaded', () => payslipsController.init());
