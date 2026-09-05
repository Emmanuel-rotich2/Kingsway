document.addEventListener('DOMContentLoaded', async () => { if (window.StaffAccess) await StaffAccess.require('staff.payslip.manage'); });
/**
 * Payslips Controller
 * Shows the logged-in staff member's own payslip history.
 * API: /api/staff/payroll-*  and  /api/finance/payrolls-*
 */

const payslipsController = {

  _staffId:  null,
  _selectedStaffId: null,
  _canSelectStaff: false,
  _activeTab: 'personal',
  _selectedEmployeeIds: new Set(),
  _payslips: [],
  _currentSlipData: null,

  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    const user = AuthContext.getUser();
    // This is a personal payslip page. A user ID is not a staff ID; using it
    // here made the page query the wrong employee (or fall through to the
    // finance-wide payroll list). Only use an explicitly linked staff record.
    this._staffId = user?.staff_id ?? user?.staff?.id ?? null;
    this._selectedStaffId = this._staffId;
    const roles = [user?.role_name, ...(Array.isArray(user?.roles) ? user.roles.map(r => r?.name || r) : [])]
      .filter(Boolean).map(r => String(r).toLowerCase());
    this._canSelectStaff = roles.some(r => ['director', 'school administrator', 'school admin', 'accountant'].includes(r));
    this._activeTab = this._canSelectStaff ? 'employees' : 'personal';

    this._populateYearFilter();
    this._applyTabState();
    await this.load();
  },

  switchTab: function (tab) {
    if (tab === 'employees' && !this._canSelectStaff) return;
    this._activeTab = tab === 'employees' ? 'employees' : 'personal';
    this._selectedStaffId = this._activeTab === 'employees' ? null : this._staffId;
    this._selectedEmployeeIds.clear();
    this._applyTabState();
    this.load();
  },

  _applyTabState: function () {
    const employeeItem = document.getElementById('employeePayslipsTabItem');
    const employeeTab = document.getElementById('employeePayslipsTab');
    const personalTab = document.getElementById('personalPayslipsTab');
    const summary = document.getElementById('psCurrentSummary');
    const p9 = document.getElementById('psP9Button');
    const view = document.getElementById('psViewSelected');
    const generate = document.getElementById('psGenerateSelectedP9');
    const print = document.getElementById('psPrintSelected');
    const email = document.getElementById('psShareEmail');
    const whatsapp = document.getElementById('psShareWhatsapp');
    if (employeeItem) employeeItem.hidden = !this._canSelectStaff;
    if (employeeTab) employeeTab.classList.toggle('active', this._activeTab === 'employees');
    if (personalTab) personalTab.classList.toggle('active', this._activeTab === 'personal');
    if (summary) summary.hidden = this._activeTab === 'employees';
    if (p9) p9.hidden = this._activeTab === 'employees';
    if (view) view.hidden = this._activeTab !== 'employees';
    if (generate) generate.hidden = this._activeTab !== 'employees';
    if (print) print.hidden = this._activeTab !== 'employees';
    if (email) email.hidden = this._activeTab !== 'employees';
    if (whatsapp) whatsapp.hidden = this._activeTab !== 'employees';
    const title = document.querySelector('#payslipsPage h3');
    if (title) title.innerHTML = this._activeTab === 'employees'
      ? '<i class="bi bi-people me-2 text-primary"></i>Employee Payslips &amp; P9 Forms'
      : '<i class="bi bi-person me-2 text-primary"></i>My Payslips &amp; P9 Forms';
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
    const thead = tbody?.closest('table')?.querySelector('thead');
    if (thead && this._activeTab === 'personal') {
      thead.innerHTML = '<tr><th>Month</th><th class="text-end">Gross Pay</th><th class="text-end">PAYE</th><th class="text-end">SHIF</th><th class="text-end">NSSF</th><th class="text-end">Net Pay</th><th>Status</th><th></th></tr>';
    }
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';

    const year = document.getElementById('psYear').value;
    const selectedStaffId = this._activeTab === 'personal' ? this._staffId : null;
    const p9 = document.getElementById('psP9Button');
    if (p9) p9.disabled = !selectedStaffId;
    if (!selectedStaffId) {
      if (this._activeTab === 'employees') {
        try {
          this._selectedEmployeeIds.clear();
          const response = await window.API.finance.getPayrollList({ year });
          const payload = response?.data ?? response;
          this._payslips = Array.isArray(payload) ? payload : (payload?.payroll || payload?.rows || []);
          this._renderEmployeeTable(tbody, this._payslips);
          this._updateBulkActions();
        } catch (e) {
          tbody.innerHTML = '<tr><td colspan="9" class="text-danger text-center py-4">Failed to load employee payslips.</td></tr>';
        }
        return;
      }
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No personal staff profile is linked to this account.</td></tr>';
      return;
    }
    try {
      const endpoint = `/staff/payroll-history?staff_id=${encodeURIComponent(selectedStaffId)}`;
      const r = await callAPI(endpoint, 'GET', null, { year });
      // This endpoint is staff-scoped and returns payroll_history. Do not use
      // /finance/payroll-list here: that endpoint intentionally returns every
      // employee for payroll administration.
      const payload = r?.data ?? r;
      this._payslips = Array.isArray(payload)
        ? payload
        : (Array.isArray(payload?.payroll_history) ? payload.payroll_history : []);

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

  _renderEmployeeTable: function (tbody, rows) {
    const fmt = n => n != null ? 'KES ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 }) : '—';
    const thead = tbody?.closest('table')?.querySelector('thead');
    if (thead) thead.innerHTML = '<tr><th class="text-center"><i class="bi bi-check2-square"></i></th><th>Employee</th><th>Period</th><th class="text-end">Gross Pay</th><th class="text-end">PAYE</th><th class="text-end">SHIF</th><th class="text-end">NSSF</th><th class="text-end">Net Pay</th><th>Status</th></tr>';
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No employee payslips found for this year.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(row => {
      const id = Number(row.id || row.payslip_id);
      const status = row.status || row.payslip_status || 'issued';
      return `<tr>
        <td class="text-center"><input type="checkbox" class="ps-employee-check" value="${id}" onchange="payslipsController.toggleEmployee(${id}, this.checked)" ${this._selectedEmployeeIds.has(id) ? 'checked' : ''}></td>
        <td><strong>${this._esc(row.staff_name || '—')}</strong><div class="small text-muted">${this._esc(row.staff_no || row.staff_number || '')}</div></td>
        <td>${this._esc(row.period_display || row.payroll_period || `${row.payroll_year}-${String(row.payroll_month).padStart(2, '0')}`)}</td>
        <td class="text-end">${fmt(row.gross_salary || row.gross_pay)}</td>
        <td class="text-end">${fmt(row.paye_tax || row.paye_deduction)}</td>
        <td class="text-end">${fmt(row.shif_contribution || row.shif_deduction || row.nhif_contribution)}</td>
        <td class="text-end">${fmt(row.nssf_contribution || row.nssf_deduction)}</td>
        <td class="text-end fw-semibold">${fmt(row.net_salary || row.net_pay)}</td>
        <td><span class="badge bg-${status === 'paid' ? 'success' : status === 'approved' ? 'primary' : 'secondary'}">${this._esc(status)}</span></td>
      </tr>`;
    }).join('');
  },

  toggleEmployee: function (id, checked) {
    if (checked) this._selectedEmployeeIds.add(Number(id));
    else this._selectedEmployeeIds.delete(Number(id));
    this._updateBulkActions();
  },

  _updateBulkActions: function () {
    const count = this._selectedEmployeeIds.size;
    const view = document.getElementById('psViewSelected');
    const generate = document.getElementById('psGenerateSelectedP9');
    const print = document.getElementById('psPrintSelected');
    const email = document.getElementById('psShareEmail');
    const whatsapp = document.getElementById('psShareWhatsapp');
    if (view) view.disabled = count < 1;
    if (generate) generate.disabled = count < 1;
    if (print) print.disabled = count < 1;
    if (email) email.disabled = count < 1;
    if (whatsapp) whatsapp.disabled = count < 1;
  },

  viewSelected: function () {
    if (!this._selectedEmployeeIds.size) {
      showNotification('Select at least one employee payslip to view.', 'warning');
      return;
    }
    const rows = this._payslips.filter(item => this._selectedEmployeeIds.has(Number(item.id || item.payslip_id)));
    const body = document.getElementById('payslipModalBody');
    body.innerHTML = rows.map(row => `<div class="border-bottom mb-4 pb-3"><h5>${this._esc(row.staff_name || 'Employee')} <small class="text-muted">${this._esc(row.period_display || '')}</small></h5>${this._renderSlipHtml(row)}</div>`).join('');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('payslipModal')).show();
  },

  printSelected: function () {
    if (!this._selectedEmployeeIds.size) return showNotification('Select payslips to print.', 'warning');
    this._selectedEmployeeIds.forEach(id => {
      const row = this._payslips.find(item => Number(item.id || item.payslip_id) === id);
      if (row) this._printRow(row);
    });
  },

  _printRow: function (row) {
    if (!window.PrintManager?.printDedicatedPayslip) return;

    // payslips has no total_deductions column. Derive the figure from the
    // authoritative stored Gross and Net so the printed document reconciles
    // with its own NET PAY line rather than implying a zero-total document.
    const grossPay = Number(row.gross_salary ?? row.gross_pay ?? 0);
    const netPay   = Number(row.net_salary ?? row.net_pay ?? 0);
    const totalDeductions = (grossPay || netPay)
      ? Math.max(0, grossPay - netPay)
      : 0;

    const statutory = {
      paye: row.paye_tax || row.paye_deduction || 0,
      nssf: row.nssf_contribution || row.nssf_deduction || 0,
      nhif_shif: row.shif_contribution || row.shif_deduction || row.nhif_contribution || 0,
      housing_levy: row.housing_levy || 0,
    };

    const parseJson = (value) => {
      if (Array.isArray(value)) return value;
      if (typeof value === 'string' && value) {
        try { const v = JSON.parse(value); return Array.isArray(v) ? v : []; }
        catch (_) { return []; }
      }
      return [];
    };
    const allowances = parseJson(row.allowances_breakdown);
    const childrenDeductions = parseJson(row.child_fees_breakdown);

    return window.PrintManager.printDedicatedPayslip({
      employeeName: row.staff_name || '',
      staffNo: row.staff_no || row.staff_number || '',
      designation: row.position || row.designation || '',
      department: row.department || row.department_name || '',
      kraPin: row.kra_pin || '',
      nssfNo: row.nssf_no || '',
      shifNo: row.shif_no || row.nhif_no || '',
      bankName: row.bank_name || '',
      bankAccountNumber: row.bank_account_number || row.bank_account || '',
      paymentMethod: row.payment_method || row.payment_mode || '',
      paymentReference: row.payment_reference || '',
      datePaid: row.payment_date || row.paid_at || '',
      status: row.payslip_status || row.payment_status || row.status || '',
      period: row.period_display || row.month_label || `${row.payroll_year}-${String(row.payroll_month).padStart(2, '0')}`,
      basicSalary: row.basic_salary || 0,
      allowances,
      statutory,
      grossPay,
      netPay,
      totalDeductions,
      employerNssf: row.employer_nssf_contribution || 0,
      employerHousing: row.employer_housing_levy || 0,
      otherDeductions: row.other_deductions_total != null ? row.other_deductions_total : null,
      childrenDeductions,
      filename: `payslip_${row.staff_no || row.staff_id}_${row.payroll_year}_${row.payroll_month}`,
    });
  },

  shareSelected: async function (channel) {
    if (!this._selectedEmployeeIds.size) return showNotification('Select payslips first.', 'warning');
    const names = this._payslips.filter(r => this._selectedEmployeeIds.has(Number(r.id || r.payslip_id))).map(r => r.staff_name).join(', ');
    const text = `Kingsway Preparatory School payslips: ${names}`;
    if (channel === 'whatsapp') window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
    else window.location.href = `mailto:?subject=${encodeURIComponent('Kingsway Preparatory School Payslips')}&body=${encodeURIComponent(text)}`;
  },

  _updateCurrentCard: function (slip) {
    const fmt  = n => n != null ? 'KES ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 }) : '—';
    const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };

    const period = slip.month_label ?? slip.period_display ?? slip.payroll_period
      ?? `${slip.payroll_year || ''}-${String(slip.payroll_month || '').padStart(2, '0')}`.replace(/^-|-$/g, '');
    setEl('psCurrentMonth', period || '—');
    setEl('psGross',        fmt(slip.gross_pay     ?? slip.gross_salary));
    setEl('psDeductions',   fmt(slip.total_deductions));
    setEl('psNet',          fmt(slip.net_pay        ?? slip.net_salary));
    setEl('psStatus',       (slip.status ?? slip.payslip_status ?? '').toUpperCase() || 'ISSUED');
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
        <td><strong>${this._esc(s.month_label ?? s.period_display ?? s.payroll_period ?? `${s.payroll_year || ''}-${String(s.payroll_month || '').padStart(2, '0')}`)}</strong></td>
        <td class="text-end">${fmt(s.gross_pay ?? s.gross_salary)}</td>
        <td class="text-end text-muted">${fmt(s.paye_tax ?? s.paye)}</td>
        <td class="text-end text-muted">${fmt(s.shif ?? s.nhif ?? s.nhif_contribution)}</td>
        <td class="text-end text-muted">${fmt(s.nssf ?? s.nssf_contribution)}</td>
        <td class="text-end fw-semibold text-success">${fmt(s.net_pay ?? s.net_salary)}</td>
        <td><span class="badge bg-${(s.status ?? s.payslip_status) === 'paid' || (s.status ?? s.payslip_status) === 'disbursed' ? 'success' : (s.status ?? s.payslip_status) === 'pending' ? 'warning' : 'secondary'}">${this._esc(s.status ?? s.payslip_status ?? 'issued')}</span></td>
        <td>
          <button class="btn btn-sm btn-outline-primary" onclick="payslipsController.viewSlip(${s.id ?? s.payslip_id ?? s.payroll_id ?? 'null'}, '${this._esc(s.month ?? s.payroll_month ?? '')}', ${s.year ?? s.payroll_year ?? new Date().getFullYear()})">
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
      if (this._selectedStaffId) params.staff_id  = this._selectedStaffId;
      if (month)         params.month     = month;
      if (year)          params.year      = year;

      const r = await callAPI('/staff/payroll-detailed-payslip?' + new URLSearchParams(params).toString(), 'GET');
      const raw = (r?.data?.data ?? r?.data ?? r);
      // Staff detailed-payslip returns { payslip, allowances_breakdown,
      // deductions_breakdown }. Normalize that contract for the view model.
      const base = raw?.payslip || raw;
      detail = {
        ...base,
        employee_name: base.employee_name || base.staff_name,
        employee_no: base.employee_no || base.staff_no,
        department: base.department || base.department_name,
        allowances: base.allowances || raw?.allowances_breakdown || [],
        deductions: base.deductions || raw?.deductions_breakdown || [],
        total_deductions: base.total_deductions ?? (
          Number(base.paye_tax || 0) + Number(base.nssf_contribution || 0) +
          Number(base.nhif_contribution || base.shif_contribution || 0) +
          Number(base.housing_levy || 0) + Number(base.loan_deduction || 0) +
          Number(base.other_deductions_total || 0)
        )
      };

      if (detail) {
        this._currentSlipData = detail;
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
    const allowances = Array.isArray(d.allowances) ? d.allowances : (Array.isArray(d.allowances_breakdown) ? d.allowances_breakdown : []);
    const deductions = Array.isArray(d.deductions) ? d.deductions : (Array.isArray(d.deductions_breakdown) ? d.deductions_breakdown : []);

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
            ${allowances.map(a => row(this._esc(a.name || a.description || a.item_name || 'Allowance'), fmt(a.amount ?? a.value))).join('')}
            <tr class="fw-bold"><td>Gross Pay</td><td class="text-end">${fmt(d.gross_pay ?? d.gross_salary)}</td></tr>
          </tbody>
        </table>

        <table class="table table-sm mt-3">
          <thead class="table-light"><tr><th>Deductions</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
            ${row('PAYE', fmt(d.paye_tax ?? d.paye), 'text-danger')}
            ${row('SHIF', fmt(d.shif ?? d.nhif ?? d.nhif_contribution), 'text-danger')}
            ${row('NSSF', fmt(d.nssf ?? d.nssf_contribution), 'text-danger')}
            ${row('Employee Housing Levy', fmt(d.housing_levy), 'text-danger')}
            ${row('Employer NSSF (school cost)', fmt(d.employer_nssf_contribution), 'text-muted')}
            ${row('Employer Housing Levy (school cost)', fmt(d.employer_housing_levy), 'text-muted')}
            ${deductions.map(x => row(this._esc(x.name || x.description || x.item_name || 'Deduction'), fmt(x.amount ?? x.value), 'text-danger')).join('')}
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
  printCurrentSlip: function () {
    if (!this._currentSlipData) {
      showNotification("No payslip to print", "warning");
      return;
    }

    const payslip = this._currentSlipData;
    const staff = payslip;

    // payslips has no total_deductions column; derive from stored Gross/Net
    // so the printed total reconciles with the NET PAY line.
    const grossPay = Number(payslip.gross_pay ?? payslip.gross_salary ?? 0);
    const netPay   = Number(payslip.net_pay ?? payslip.net_salary ?? 0);
    const totalDeductions = (grossPay || netPay) ? Math.max(0, grossPay - netPay) : 0;

    if (window.PrintManager && window.PrintManager.printDedicatedPayslip) {
      window.PrintManager.printDedicatedPayslip({
        employeeName: staff.employee_name || staff.staff_name || `${staff.first_name || ''} ${staff.last_name || ''}`.trim(),
        staffNo: staff.staff_no || '',
        department: staff.department_name || staff.department || '',
        designation: staff.designation || staff.position || '',
        kraPin: staff.kra_pin || '',
        nssfNo: staff.nssf_no || '',
        shifNo: staff.shif_no || staff.nhif_no || '',
        bankName: staff.bank_name || '',
        bankAccountNumber: staff.bank_account_number || staff.bank_account || '',
        paymentMethod: staff.payment_method || staff.payment_mode || '',
        paymentReference: staff.payment_reference || '',
        datePaid: staff.payment_date || staff.paid_at || '',
        status: staff.payslip_status || staff.payment_status || staff.status || '',
        period: payslip.period || payslip.payroll_period || `${payslip.payroll_year || new Date().getFullYear()}-${String(payslip.payroll_month || new Date().getMonth() + 1).padStart(2, '0')}`,
        basicSalary: payslip.basic_salary || payslip.basic_pay || 0,
        allowances: (payslip.earnings || []).map(e => ({ name: e.description || e.name || '', amount: e.amount || e.value || 0 })),
        deductions: (payslip.deductions || []).map(d => ({ name: d.description || d.name || '', amount: d.amount || d.value || 0 })),
        statutory: {
          paye: payslip.paye || payslip.tax || 0,
          nssf: payslip.nssf || 0,
          nhif_shif: payslip.nhif || payslip.shif || 0,
          housing_levy: payslip.housing_levy || 0,
        },
        grossPay,
        netPay,
        totalDeductions,
        employerNssf: payslip.employer_nssf_contribution || 0,
        employerHousing: payslip.employer_housing_levy || 0,
        otherDeductions: payslip.other_deductions_total != null ? payslip.other_deductions_total : null,
        childrenDeductions: Array.isArray(payslip.children_deductions) ? payslip.children_deductions : [],
        filename: `payslip_${staff.staff_no || staff.id || 'staff'}_${payslip.period || new Date().toISOString().slice(0, 7)}`,
      });
    } else {
      showNotification("PrintManager not available", "error");
    }
  },

  downloadP9: async function () {
    try {
      const year = document.getElementById('psYear').value;
      let staffId = this._activeTab === 'employees' ? null : this._staffId;
      if (this._activeTab === 'employees') {
        if (!this._selectedEmployeeIds.size) {
          showNotification('Select at least one employee before generating P9 forms.', 'warning');
          return;
        }
        const rows = this._payslips.filter(item => this._selectedEmployeeIds.has(Number(item.id || item.payslip_id)));
        for (const row of rows) {
          await this._generateP9ForStaff(Number(row.staff_id), year, row);
        }
        return;
      }
      if (!staffId || !this._payslips.length) {
        showNotification('Select an employee with payroll data before generating a P9.', 'warning');
        return;
      }

      if (window.PrintManager && window.PrintManager.printP9Form) {
        const payslip = this._currentSlipData || {};
        const staff = payslip;
        await this._generateP9ForStaff(staffId, year, staff);
      } else {
        const params = new URLSearchParams({ year });
        params.set('staff_id', staffId);
        const url = (window.APP_BASE || '') + '/api/staff/payroll-download-p9?' + params.toString();
        window.open(url, '_blank');
      }
    } catch (e) { showNotification('P9 download failed', 'error'); }
  },

  _generateP9ForStaff: async function (staffId, year, staff = {}) {
    if (!staffId || !window.PrintManager?.printP9Form) return;
    return window.PrintManager.printP9Form({
      employeeName: staff.employee_name || staff.staff_name || '',
      staffNo: staff.staff_no || staff.staff_number || '',
      kraPin: staff.kra_pin || '',
      department: staff.department_name || staff.department || '',
      designation: staff.designation || staff.position || '',
      taxYear: year,
      orientation: 'landscape',
      paperSize: 'A4',
      staff_id: staffId,
      employerName: 'Kingsway Preparatory School',
      employerPin: '',
      employerKraAddress: '',
      monthlyData: staff.monthly_payslips || [],
      basicSalary: staff.basic_salary || 0,
      grossPay: staff.gross_pay || staff.gross_salary || 0,
      totalPaye: staff.paye_tax || staff.paye || staff.paye_deduction || 0,
      totalNssf: staff.nssf || staff.nssf_contribution || staff.nssf_deduction || 0,
      totalNhifShif: staff.nhif || staff.shif || staff.shif_contribution || staff.shif_deduction || 0,
      totalHousingLevy: staff.housing_levy || 0,
      filename: `P9_${staff.staff_no || staff.staff_number || staffId || 'staff'}_${year}`,
    });
  },

  _esc: function (str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  },
};

document.addEventListener('DOMContentLoaded', () => payslipsController.init());

window.payslipsController = payslipsController;
