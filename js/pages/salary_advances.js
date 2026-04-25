/**
 * Salary Advances Controller
 * API: GET/POST /finance/salary-advances
 *      PUT      /finance/salary-advances/{id}
 */
const salaryAdvancesController = {
  _data: [], _filtered: [], _page: 1, _perPage: 20,
  _modal: null, _approveModal: null,
  _canApprove: false, _canCreate: false,

  init: async function () {
    if (!AuthContext.isAuthenticated()) return;
    this._canCreate  = AuthContext.hasPermission('finance.create') || AuthContext.hasPermission('payroll.create');
    this._canApprove = AuthContext.hasPermission('finance.approve') || AuthContext.hasPermission('payroll.approve');
    this._modal        = new bootstrap.Modal(document.getElementById('advanceModal'));
    this._approveModal = new bootstrap.Modal(document.getElementById('approveAdvModal'));

    const addBtn = document.getElementById('addAdvanceBtn');
    if (addBtn) addBtn.style.display = this._canCreate ? '' : 'none';

    this._bindEvents();
    await Promise.all([this._loadStaff(), this._load()]);
  },

  _bindEvents: function () {
    ['advSearch', 'advStatusFilter'].forEach(id => {
      document.getElementById(id)?.addEventListener('input',  () => this._applyFilters());
      document.getElementById(id)?.addEventListener('change', () => this._applyFilters());
    });
  },

  _load: async function () {
    const tbody = document.getElementById('advTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-warning"></div></td></tr>';
    try {
      const r = await callAPI('/finance/salary-advances', 'GET');
      const resp = r?.data || r;
      this._data = Array.isArray(resp?.advances) ? resp.advances : (Array.isArray(resp) ? resp : []);
      this._setStats(resp?.stats || {});
      this._filtered = [...this._data];
      this._page = 1;
      this._renderTable();
    } catch (e) {
      if (tbody) tbody.innerHTML = `<tr><td colspan="10" class="text-danger text-center py-4">${e.message || 'Load failed'}</td></tr>`;
    }
  },

  _loadStaff: async function () {
    try {
      const r = await callAPI('/staff/all', 'GET');
      const list = r?.data || r || [];
      const opts = list.map(s => `<option value="${s.id}">${s.full_name || (s.first_name + ' ' + s.last_name)} (${s.employee_number || 'EMP'})</option>`).join('');
      document.getElementById('adv_staff_id') && (document.getElementById('adv_staff_id').innerHTML = '<option value="">— Select staff —</option>' + opts);
    } catch (e) { /* non-fatal */ }
  },

  _setStats: function (stats) {
    const fmt = v => 'KES ' + Number(v || 0).toLocaleString('en-KE', {minimumFractionDigits: 0});
    this._set('statTotalIssued',      fmt(stats.total_issued     || 0));
    this._set('statTotalOutstanding', fmt(stats.total_outstanding || 0));
    this._set('statPendingCount',     stats.pending_approval     || 0);
    this._set('statTotalCount',       stats.total_advances       || 0);
  },

  _applyFilters: function () {
    const q  = (document.getElementById('advSearch')?.value       || '').toLowerCase();
    const st = document.getElementById('advStatusFilter')?.value  || '';
    this._filtered = this._data.filter(a => {
      if (st && a.status !== st) return false;
      if (q && !(a.staff_name || '').toLowerCase().includes(q)
           && !(a.employee_number || '').toLowerCase().includes(q)
           && !(a.advance_number || '').toLowerCase().includes(q)) return false;
      return true;
    });
    this._page = 1;
    this._renderTable();
  },

  clearFilters: function () {
    ['advSearch', 'advStatusFilter'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    this._applyFilters();
  },

  _renderTable: function () {
    const tbody = document.getElementById('advTableBody');
    if (!tbody) return;
    const start = (this._page - 1) * this._perPage;
    const page  = this._filtered.slice(start, start + this._perPage);
    const info  = document.getElementById('advTableInfo');
    if (info) info.textContent = `Showing ${start + 1}–${Math.min(start + page.length, this._filtered.length)} of ${this._filtered.length}`;

    if (!page.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No advances found.</td></tr>';
      this._setPagination(); return;
    }

    const stBadge = {
      pending: 'secondary', active: 'warning', fully_deducted: 'success',
      rejected: 'danger', approved: 'primary', cancelled: 'dark'
    };
    const schLabels = {single_month: '1 month', two_months: '2 months', three_months: '3 months'};

    tbody.innerHTML = page.map(a => {
      const actions = [];
      if (a.status === 'pending' && this._canApprove) {
        actions.push(`<button class="btn btn-xs btn-outline-success py-0 px-1" onclick="salaryAdvancesController.showApproval(${a.id},'approve')">Approve</button>`);
        actions.push(`<button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="salaryAdvancesController.showApproval(${a.id},'reject')">Reject</button>`);
      }
      if (a.status === 'active' && this._canApprove) {
        actions.push(`<button class="btn btn-xs btn-outline-warning py-0 px-1" onclick="salaryAdvancesController.recordDeduction(${a.id})">Deduct</button>`);
      }

      return `<tr>
        <td><small class="fw-mono">${this._esc(a.advance_number || '—')}</small></td>
        <td>${this._esc(a.staff_name || '—')} <small class="text-muted d-block">${this._esc(a.employee_number || '')}</small></td>
        <td class="text-end">KES ${Number(a.requested_amount || 0).toLocaleString()}</td>
        <td class="text-end ${a.approved_amount ? 'text-success fw-bold' : 'text-muted'}">
          ${a.approved_amount ? 'KES ' + Number(a.approved_amount).toLocaleString() : '—'}
        </td>
        <td>${schLabels[a.deduction_schedule] || a.deduction_schedule || '—'}</td>
        <td>${a.deduction_start_month ? a.deduction_start_month.slice(0, 7) : '—'}</td>
        <td class="text-end">${a.amount_deducted > 0 ? 'KES ' + Number(a.amount_deducted).toLocaleString() : '—'}</td>
        <td class="text-end ${a.balance_remaining > 0 ? 'text-danger fw-bold' : 'text-muted'}">
          ${a.balance_remaining > 0 ? 'KES ' + Number(a.balance_remaining).toLocaleString() : (a.status === 'fully_deducted' ? '✓ Cleared' : '—')}
        </td>
        <td><span class="badge bg-${stBadge[a.status] || 'secondary'}">${a.status || '—'}</span></td>
        <td class="text-end">${actions.join(' ')}</td>
      </tr>`;
    }).join('');
    this._setPagination();
  },

  _setPagination: function () {
    const pg = document.getElementById('advPagination');
    if (!pg) return;
    const pages = Math.ceil(this._filtered.length / this._perPage);
    if (pages <= 1) { pg.innerHTML = ''; return; }
    pg.innerHTML = Array.from({length: pages}, (_, i) =>
      `<li class="page-item ${i+1===this._page?'active':''}">
         <button class="page-link" onclick="salaryAdvancesController._goPage(${i+1})">${i+1}</button>
       </li>`
    ).join('');
  },

  _goPage: function (p) { this._page = p; this._renderTable(); },

  showModal: function () {
    document.getElementById('adv_id').value     = '';
    document.getElementById('adv_staff_id').value = '';
    document.getElementById('adv_amount').value = '';
    document.getElementById('adv_reason').value = '';
    document.getElementById('adv_schedule').value = 'single_month';
    this._modal.show();
  },

  save: async function () {
    const staffId = document.getElementById('adv_staff_id')?.value;
    const amount  = document.getElementById('adv_amount')?.value;
    const reason  = document.getElementById('adv_reason')?.value.trim();
    if (!staffId || !amount || !reason) {
      showNotification('Staff, amount, and reason are required.', 'warning'); return;
    }
    try {
      await callAPI('/finance/salary-advances', 'POST', {
        staff_id: staffId,
        requested_amount: parseFloat(amount),
        reason,
        deduction_schedule: document.getElementById('adv_schedule')?.value || 'single_month',
      });
      showNotification('Advance request submitted.', 'success');
      this._modal.hide();
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Save failed.', 'danger');
    }
  },

  showApproval: function (id, action) {
    const advance = this._data.find(a => a.id == id);
    if (!advance) return;

    document.getElementById('approve_adv_id').value     = id;
    document.getElementById('approve_adv_action').value = action;

    const isApprove = action === 'approve';
    const header = document.getElementById('approveAdvHeader');
    const title  = document.getElementById('approveAdvTitle');
    const btn    = document.getElementById('approveAdvBtn');
    header.className = 'modal-header bg-' + (isApprove ? 'success' : 'danger') + ' text-white';
    title.textContent = isApprove ? 'Approve Advance' : 'Reject Advance';
    btn.className     = 'btn btn-' + (isApprove ? 'success' : 'danger');
    btn.textContent   = isApprove ? 'Approve' : 'Reject';

    document.getElementById('approveFields').style.display = isApprove ? '' : 'none';
    document.getElementById('approveNotesLabel').textContent = isApprove ? 'Notes (optional)' : 'Rejection Reason';

    document.getElementById('advSummaryBox').innerHTML = `
      <div class="row g-1">
        <div class="col-6"><strong>Staff:</strong> ${this._esc(advance.staff_name)}</div>
        <div class="col-6"><strong>Amount:</strong> KES ${Number(advance.requested_amount).toLocaleString()}</div>
        <div class="col-6"><strong>Schedule:</strong> ${advance.deduction_schedule}</div>
        <div class="col-6"><strong>Reason:</strong> ${this._esc(advance.reason || '—')}</div>
      </div>`;

    if (isApprove) {
      document.getElementById('approve_amount').value = advance.requested_amount;
      const nextMonth = new Date();
      nextMonth.setMonth(nextMonth.getMonth() + 1);
      document.getElementById('approve_start_month').value = nextMonth.toISOString().slice(0, 7);
    }
    document.getElementById('approve_notes').value = '';
    this._approveModal.show();
  },

  confirmApproval: async function () {
    const id     = document.getElementById('approve_adv_id').value;
    const action = document.getElementById('approve_adv_action').value;
    const notes  = document.getElementById('approve_notes').value;

    const payload = { action, notes };
    if (action === 'approve') {
      payload.approved_amount       = parseFloat(document.getElementById('approve_amount').value);
      payload.deduction_start_month = document.getElementById('approve_start_month').value + '-01';
    } else {
      payload.reason = notes;
    }

    try {
      await callAPI('/finance/salary-advances/' + id, 'PUT', payload);
      showNotification(action === 'approve' ? 'Advance approved.' : 'Advance rejected.', action === 'approve' ? 'success' : 'info');
      this._approveModal.hide();
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Action failed.', 'danger');
    }
  },

  recordDeduction: async function (id) {
    const advance = this._data.find(a => a.id == id);
    if (!advance) return;
    const amt = advance.amount_per_deduction || advance.balance_remaining;
    if (!confirm(`Record deduction of KES ${Number(amt).toLocaleString()} for ${advance.staff_name}?`)) return;
    try {
      await callAPI('/finance/salary-advances/' + id, 'PUT', { action: 'record_deduction', amount: amt });
      showNotification('Deduction recorded.', 'success');
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Deduction failed.', 'danger');
    }
  },

  _set: (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};
