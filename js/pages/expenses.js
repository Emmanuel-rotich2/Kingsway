/**
 * Expenses Controller — manage_expenses.php
 * Full CRUD + multi-stage approval workflow.
 * API: GET/POST/PUT/DELETE /finance/expenses
 *      GET /finance/expense-categories
 *
 * Roles & permissions:
 *   expenses.create  → accountant, bursar, department heads
 *   expenses.approve → finance manager, director
 *   expenses.pay     → accountant (after approval)
 *   expenses.view    → all finance roles
 */
const expensesController = {
  _data: [],
  _filtered: [],
  _categories: [],
  _page: 1,
  _perPage: 20,
  _modal: null,
  _approvalModal: null,

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._modal         = new bootstrap.Modal(document.getElementById('expenseModal'));
    this._approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));
    this._setupPermissions();
    this._bindEvents();
    await Promise.all([this._loadCategories(), this._loadBudgetLines(), this._load()]);
  },

  // Show/hide UI elements based on permissions
  _setupPermissions: function () {
    const canCreate  = AuthContext.hasPermission('expenses.create')  || AuthContext.hasPermission('expenses_create')
                    || AuthContext.hasPermission('finance.create')   || AuthContext.hasPermission('finance_create');
    const canApprove = AuthContext.hasPermission('expenses.approve') || AuthContext.hasPermission('expenses_approve')
                    || AuthContext.hasPermission('finance.approve')  || AuthContext.hasPermission('finance_approve');

    const addBtn = document.getElementById('addExpenseBtn');
    if (addBtn) addBtn.style.display = canCreate ? '' : 'none';

    // Store for table rendering
    this._canCreate  = canCreate;
    this._canApprove = canApprove;
    this._canPay     = canApprove; // same role can mark as paid
  },

  _bindEvents: function () {
    ['expSearch', 'expCategoryFilter', 'expStatusFilter', 'expDateFrom', 'expDateTo'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', () => this._applyFilters());
      if (el) el.addEventListener('change', () => this._applyFilters());
    });
  },

  // ── Data Loading ──────────────────────────────────────────────────────────
  _load: async function () {
    const tbody = document.getElementById('expensesTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-danger"></div><span class="ms-2 text-muted">Loading…</span></td></tr>';
    try {
      const r = await callAPI('/finance/expenses', 'GET');
      const resp = r?.data || r;
      this._data = Array.isArray(resp?.expenses) ? resp.expenses : (Array.isArray(resp) ? resp : []);
      const stats = resp?.stats || {};
      this._setStats(stats);
      this._filtered = [...this._data];
      this._page = 1;
      this._renderTable();
    } catch (e) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-danger text-center py-4">Failed to load expenses. ' + (e.message || '') + '</td></tr>';
    }
  },

  _loadCategories: async function () {
    try {
      const r = await callAPI('/finance/expense-categories', 'GET');
      this._categories = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._populateCategoryDropdowns();
    } catch (e) { /* non-fatal */ }
  },

  _populateCategoryDropdowns: function () {
    const opts = this._categories.map(c =>
      `<option value="${c.id}" data-type="${c.type}">${c.name}</option>`
    ).join('');
    const filterSel = document.getElementById('expCategoryFilter');
    if (filterSel) filterSel.innerHTML = '<option value="">All Categories</option>' + opts;
    const modalSel = document.getElementById('exp_category_id');
    if (modalSel) modalSel.innerHTML = '<option value="">Select category…</option>' + opts;
  },

  _loadBudgetLines: async function () {
    try {
      const r = await callAPI('/finance/budgets', 'GET');
      const budgets = Array.isArray(r?.data) ? r.data : [];
      const sel = document.getElementById('exp_budget_line_item_id');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Not linked to budget —</option>';
      budgets.forEach(b => {
        if (b.status === 'active') {
          const opt = document.createElement('option');
          opt.value = b.id;
          opt.textContent = b.name + ' (' + b.academic_year + ')';
          sel.appendChild(opt);
        }
      });
    } catch (e) { /* non-fatal */ }
  },

  // ── Stats ─────────────────────────────────────────────────────────────────
  _setStats: function (stats) {
    const fmt = v => 'KES ' + Number(v || 0).toLocaleString('en-KE', {minimumFractionDigits: 2});
    this._set('statTotalExpenses',  fmt(stats.approved_amount || stats.total_amount || 0));
    this._set('statPendingAmount',  fmt(stats.pending_amount  || 0));
    this._set('statApprovedAmount', fmt(stats.approved_amount || 0));
    this._set('statThisMonth',      fmt(stats.this_month      || 0));
  },

  // ── Filters ───────────────────────────────────────────────────────────────
  _applyFilters: function () {
    const q   = (document.getElementById('expSearch')?.value         || '').toLowerCase();
    const cat = document.getElementById('expCategoryFilter')?.value  || '';
    const st  = document.getElementById('expStatusFilter')?.value    || '';
    const df  = document.getElementById('expDateFrom')?.value        || '';
    const dt  = document.getElementById('expDateTo')?.value          || '';

    this._filtered = this._data.filter(e => {
      if (q   && !(e.description||'').toLowerCase().includes(q) && !(e.vendor_name||'').toLowerCase().includes(q) && !(e.expense_number||'').toLowerCase().includes(q)) return false;
      if (cat && String(e.category_id) !== cat) return false;
      if (st  && e.status !== st) return false;
      if (df  && (e.expense_date||'') < df) return false;
      if (dt  && (e.expense_date||'') > dt) return false;
      return true;
    });
    this._page = 1;
    this._renderTable();
  },

  clearFilters: function () {
    ['expSearch', 'expCategoryFilter', 'expStatusFilter', 'expDateFrom', 'expDateTo'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    this._applyFilters();
  },

  // ── Table Render ──────────────────────────────────────────────────────────
  _renderTable: function () {
    const tbody = document.getElementById('expensesTableBody');
    if (!tbody) return;
    const start = (this._page - 1) * this._perPage;
    const page  = this._filtered.slice(start, start + this._perPage);

    if (!page.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No expenses found.</td></tr>';
      this._setInfo(0); this._setPagination(); return;
    }

    const stCls  = {draft:'secondary', pending_approval:'warning', approved:'success', paid:'primary', rejected:'danger', cancelled:'dark'};
    const stLabel= {draft:'Draft', pending_approval:'Pending Approval', approved:'Approved', paid:'Paid', rejected:'Rejected', cancelled:'Cancelled'};

    tbody.innerHTML = page.map(e => {
      const st = e.status || 'draft';
      const actions = this._buildActions(e, st);
      return `<tr>
        <td><small class="font-monospace text-muted">${this._esc(e.expense_number || '—')}</small></td>
        <td>${this._esc(e.expense_date || '—')}</td>
        <td><span class="badge rounded-pill bg-light text-dark border">${this._esc(e.category_name || '—')}</span></td>
        <td>${this._esc(e.description || '—')}</td>
        <td>${this._esc(e.vendor_name || '—')}</td>
        <td class="text-end fw-bold">KES ${Number(e.amount||0).toLocaleString()}</td>
        <td>${this._payMethodBadge(e.payment_method)}</td>
        <td><small>${this._esc(e.recorded_by_name || '—')}</small></td>
        <td><span class="badge bg-${stCls[st]||'secondary'}">${stLabel[st]||st}</span></td>
        <td class="text-end">${actions}</td>
      </tr>`;
    }).join('');

    this._setInfo(this._filtered.length);
    this._setPagination();
  },

  _buildActions: function (e, st) {
    let btns = `<button class="btn btn-sm btn-outline-secondary py-0" onclick="expensesController.edit(${e.id})" title="View/Edit"><i class="bi bi-pencil"></i></button>`;

    if (st === 'draft' && this._canCreate) {
      btns += ` <button class="btn btn-sm btn-outline-warning py-0" onclick="expensesController.submitForApproval(${e.id})" title="Submit for approval"><i class="bi bi-send"></i></button>`;
    }
    if (st === 'pending_approval' && this._canApprove) {
      btns += ` <button class="btn btn-sm btn-success py-0" onclick="expensesController.openApproval(${e.id},'approve')" title="Approve"><i class="bi bi-check-lg"></i></button>`;
      btns += ` <button class="btn btn-sm btn-danger py-0" onclick="expensesController.openApproval(${e.id},'reject')" title="Reject"><i class="bi bi-x-lg"></i></button>`;
    }
    if (st === 'approved' && this._canPay) {
      btns += ` <button class="btn btn-sm btn-primary py-0" onclick="expensesController.openApproval(${e.id},'pay')" title="Mark as Paid"><i class="bi bi-wallet2"></i></button>`;
    }
    return btns;
  },

  _payMethodBadge: function (m) {
    const map = {cash:'success', mpesa:'warning', bank_transfer:'info', cheque:'secondary', direct_debit:'primary'};
    const label = {cash:'Cash', mpesa:'M-Pesa', bank_transfer:'Bank', cheque:'Cheque', direct_debit:'Direct Debit'};
    return `<span class="badge bg-${map[m]||'light'} text-dark">${label[m]||m||'—'}</span>`;
  },

  _setInfo: function (total) {
    const el = document.getElementById('expTableInfo');
    if (!el) return;
    if (!total) { el.textContent = 'No records'; return; }
    const s = (this._page-1)*this._perPage+1, e2 = Math.min(this._page*this._perPage, total);
    el.textContent = `Showing ${s}–${e2} of ${total}`;
  },

  _setPagination: function () {
    const pg = document.getElementById('expPagination');
    if (!pg) return;
    const pages = Math.ceil(this._filtered.length / this._perPage);
    if (pages <= 1) { pg.innerHTML = ''; return; }
    pg.innerHTML = Array.from({length: pages}, (_, i) =>
      `<li class="page-item ${i+1===this._page?'active':''}">
         <button class="page-link" onclick="expensesController._goPage(${i+1})">${i+1}</button>
       </li>`
    ).join('');
  },

  _goPage: function (p) { this._page = p; this._renderTable(); },

  // ── Modal: Create / Edit ──────────────────────────────────────────────────
  showModal: function (exp = null) {
    document.getElementById('expenseModalTitle').innerHTML =
      '<i class="bi bi-receipt me-2"></i>' + (exp ? 'Edit Expense' : 'Record Expense');
    document.getElementById('exp_id').value                  = exp?.id            || '';
    document.getElementById('exp_category_id').value         = exp?.category_id   || '';
    document.getElementById('exp_amount').value              = exp?.amount         || '';
    document.getElementById('exp_date').value                = exp?.expense_date   || new Date().toISOString().split('T')[0];
    document.getElementById('exp_payment_method').value      = exp?.payment_method || 'cash';
    document.getElementById('exp_vendor_name').value         = exp?.vendor_name    || '';
    document.getElementById('exp_receipt_number').value      = exp?.receipt_number || '';
    document.getElementById('exp_reference_number').value    = exp?.reference_number|| '';
    document.getElementById('exp_budget_line_item_id').value = exp?.budget_line_item_id || '';
    document.getElementById('exp_description').value         = exp?.description    || '';
    document.getElementById('exp_notes').value               = exp?.notes          || '';
    this._modal.show();
  },

  edit: function (id) {
    const exp = this._data.find(e => e.id == id);
    if (exp) this.showModal(exp);
  },

  _collectForm: function () {
    return {
      category_id:          document.getElementById('exp_category_id')?.value         || null,
      amount:               document.getElementById('exp_amount')?.value              || null,
      expense_date:         document.getElementById('exp_date')?.value                || null,
      payment_method:       document.getElementById('exp_payment_method')?.value      || 'cash',
      vendor_name:          document.getElementById('exp_vendor_name')?.value         || null,
      receipt_number:       document.getElementById('exp_receipt_number')?.value      || null,
      reference_number:     document.getElementById('exp_reference_number')?.value    || null,
      budget_line_item_id:  document.getElementById('exp_budget_line_item_id')?.value || null,
      description:          document.getElementById('exp_description')?.value.trim()  || null,
      notes:                document.getElementById('exp_notes')?.value               || null,
    };
  },

  saveDraft: async function () {
    await this._save('draft');
  },

  saveAndSubmit: async function () {
    await this._save('pending_approval');
  },

  _save: async function (targetStatus) {
    const payload = this._collectForm();
    if (!payload.category_id || !payload.amount || !payload.expense_date || !payload.description) {
      showNotification('Category, Amount, Date, and Description are required.', 'warning');
      return;
    }
    const id = document.getElementById('exp_id')?.value;
    try {
      if (id) {
        await callAPI('/finance/expenses/' + id, 'PUT', {...payload, status: targetStatus});
      } else {
        await callAPI('/finance/expenses', 'POST', payload);
        if (targetStatus === 'pending_approval') {
          // server creates as draft, then we submit
        }
      }
      showNotification('Expense saved' + (targetStatus === 'pending_approval' ? ' and submitted for approval' : ' as draft') + '.', 'success');
      this._modal.hide();
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Save failed.', 'danger');
    }
  },

  submitForApproval: async function (id) {
    if (!confirm('Submit this expense for approval?')) return;
    try {
      await callAPI('/finance/expenses/' + id, 'PUT', {status: 'pending_approval'});
      showNotification('Expense submitted for approval.', 'success');
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Failed.', 'danger');
    }
  },

  // ── Approval Modal ────────────────────────────────────────────────────────
  openApproval: function (id, action) {
    const exp = this._data.find(e => e.id == id);
    if (!exp) return;

    document.getElementById('approval_expense_id').value = id;
    document.getElementById('approval_action').value     = action;
    document.getElementById('approval_notes').value      = '';

    const isReject = action === 'reject';
    const isPay    = action === 'pay';
    const hdr      = document.getElementById('approvalModalHeader');
    const btn      = document.getElementById('approvalConfirmBtn');
    const notesLbl = document.getElementById('approvalNotesLabel');

    hdr.className = 'modal-header ' + (isReject ? 'bg-danger text-white' : isPay ? 'bg-primary text-white' : 'bg-success text-white');
    document.getElementById('approvalModalTitle').textContent = isReject ? 'Reject Expense' : isPay ? 'Mark as Paid' : 'Approve Expense';
    btn.className = 'btn btn-' + (isReject ? 'danger' : isPay ? 'primary' : 'success');
    btn.textContent = isReject ? 'Reject' : isPay ? 'Mark Paid' : 'Approve';
    notesLbl.textContent = isReject ? 'Reason for rejection *' : 'Notes (optional)';

    document.getElementById('approvalAmount').textContent   = 'KES ' + Number(exp.amount||0).toLocaleString();
    document.getElementById('approvalCategory').textContent = exp.category_name || '—';
    document.getElementById('approvalDate').textContent     = exp.expense_date   || '—';
    document.getElementById('approvalVendor').textContent   = exp.vendor_name    || '—';
    document.getElementById('approvalDesc').textContent     = exp.description    || '—';

    this._approvalModal.show();
  },

  confirmApproval: async function () {
    const id     = document.getElementById('approval_expense_id').value;
    const action = document.getElementById('approval_action').value;
    const notes  = document.getElementById('approval_notes').value.trim();

    if (action === 'reject' && !notes) {
      showNotification('Please provide a reason for rejection.', 'warning');
      return;
    }

    const statusMap = {approve: 'approved', reject: 'rejected', pay: 'paid'};
    try {
      await callAPI('/finance/expenses/' + id, 'PUT', {
        status:           statusMap[action],
        rejection_reason: action === 'reject' ? notes : undefined,
      });
      showNotification('Expense ' + (statusMap[action]) + ' successfully.', 'success');
      this._approvalModal.hide();
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Action failed.', 'danger');
    }
  },

  // ── Export ────────────────────────────────────────────────────────────────
  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.', 'warning'); return; }
    const headers = ['Ref No','Date','Category','Description','Vendor','Amount','Method','Recorded By','Status'];
    const rows = this._filtered.map(e => [
      `"${e.expense_number||''}"`, `"${e.expense_date||''}"`, `"${e.category_name||''}"`,
      `"${(e.description||'').replace(/"/g,"'")}"`, `"${e.vendor_name||''}"`,
      e.amount||0, `"${e.payment_method||''}"`, `"${e.recorded_by_name||''}"`, `"${e.status||''}"`
    ].join(','));
    const blob = new Blob([[headers.join(','), ...rows].join('\n')], {type: 'text/csv'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'expenses_' + new Date().toISOString().slice(0,10) + '.csv'; a.click();
  },

  // ── Helpers ───────────────────────────────────────────────────────────────
  _set: (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};
